<?php

namespace App\Domain\Queue;

use App\Domain\Access\AuditLogger;
use App\Domain\Organization\EmployeeStatus;
use App\Domain\Organization\Events\EmployeeStatusChanged;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Employee;
use App\Domain\Organization\Models\Location;
use App\Domain\Queue\Exceptions\QueueActionException;
use App\Domain\Queue\Models\Ticket;
use App\Domain\Queue\Models\TicketEvent;
use Illuminate\Support\Carbon;

/**
 * The only writer of ticket state (staff-queue "Ticket lifecycle"). Every
 * method runs under the location's QueueLock, validates the transition,
 * updates timing columns, records a ticket event + audit entry, and keeps
 * the serving employee's Busy/Available status in step.
 */
class TicketStateMachine
{
    public function __construct(
        private readonly QueueLock $lock,
        private readonly QueueOrdering $ordering,
        private readonly AuditLogger $audit,
    ) {}

    /** Atomically claim the next ticket this employee may serve, or null. */
    public function callNext(Employee $employee, Location $location, Actor $actor): ?Ticket
    {
        return $this->lock->run($location, function () use ($employee, $location, $actor) {
            $employee->refresh();
            $this->assertCanTakeTicket($employee, $location);

            $ticket = $this->ordering->offerableTo($employee, $location->id)->first();

            return $ticket ? $this->doCall($ticket, $employee, $actor, 'called') : null;
        });
    }

    /** Call a specific waiting ticket for an employee (out of order). */
    public function call(Ticket $ticket, Employee $employee, Actor $actor): Ticket
    {
        return $this->locked($ticket, function (Ticket $ticket) use ($employee, $actor) {
            $employee->refresh();
            $this->assertStatus($ticket, TicketStatus::Waiting);
            $this->assertCanTakeTicket($employee, $ticket->location);

            return $this->doCall($ticket, $employee, $actor, 'called');
        });
    }

    /** Announce a called ticket again (lobby highlight + reminder). */
    public function recall(Ticket $ticket, Actor $actor): Ticket
    {
        return $this->locked($ticket, function (Ticket $ticket) use ($actor) {
            $this->assertStatus($ticket, TicketStatus::Called);
            $ticket->forceFill(['called_at' => now(), 'recall_count' => $ticket->recall_count + 1])->save();
            $this->record($ticket, 'recalled', TicketStatus::Called, TicketStatus::Called, $actor, employeeId: $ticket->serving_employee_id, deskId: $ticket->desk_id);

            return $ticket;
        });
    }

    /** Put a called ticket back in the queue (keeps its place). */
    public function requeue(Ticket $ticket, Actor $actor): Ticket
    {
        return $this->locked($ticket, function (Ticket $ticket) use ($actor) {
            $employeeId = $ticket->serving_employee_id;
            $this->transition($ticket, TicketStatus::Waiting, 'requeued', $actor, [
                'serving_employee_id' => null, 'desk_id' => null, 'called_at' => null,
            ]);
            $this->releaseEmployee($employeeId);

            return $ticket;
        });
    }

    public function start(Ticket $ticket, Actor $actor): Ticket
    {
        return $this->locked($ticket, function (Ticket $ticket) use ($actor) {
            return $this->transition($ticket, TicketStatus::InService, 'started', $actor, ['service_started_at' => now()]);
        });
    }

    public function complete(Ticket $ticket, Actor $actor, ?string $outcome = null): Ticket
    {
        return $this->locked($ticket, function (Ticket $ticket) use ($actor, $outcome) {
            $now = now();
            $employeeId = $ticket->serving_employee_id;
            $this->transition($ticket, TicketStatus::Completed, 'completed', $actor, [
                'completed_at' => $now,
                'service_seconds' => $this->serviceSeconds($ticket, $now),
                'outcome' => $outcome,
            ], meta: $outcome ? ['outcome' => $outcome] : []);
            $this->releaseEmployee($employeeId);

            return $ticket;
        });
    }

    public function hold(Ticket $ticket, Actor $actor, ?string $reason = null): Ticket
    {
        return $this->locked($ticket, function (Ticket $ticket) use ($actor, $reason) {
            $now = now();
            $employeeId = $ticket->serving_employee_id;
            $changes = ['hold_started_at' => $now, 'hold_reason' => $reason];
            if ($ticket->status === TicketStatus::InService) {
                $changes += [
                    'service_seconds' => $this->serviceSeconds($ticket, $now),
                    'service_started_at' => null,
                    'serving_employee_id' => null,
                    'desk_id' => null,
                ];
            }
            $this->transition($ticket, TicketStatus::OnHold, 'held', $actor, $changes, meta: $reason ? ['reason' => $reason] : []);
            $this->releaseEmployee($employeeId);

            return $ticket;
        });
    }

    public function release(Ticket $ticket, Actor $actor): Ticket
    {
        return $this->locked($ticket, function (Ticket $ticket) use ($actor) {
            $held = $ticket->hold_started_at ? (int) $ticket->hold_started_at->diffInSeconds(now()) : 0;

            return $this->transition($ticket, TicketStatus::Waiting, 'released', $actor, [
                'hold_started_at' => null,
                'hold_reason' => null,
                'total_hold_seconds' => $ticket->total_hold_seconds + $held,
            ]);
        });
    }

    /** Assign a waiting ticket to a specific employee (null = unassign). */
    public function assign(Ticket $ticket, ?Employee $employee, Actor $actor): Ticket
    {
        return $this->locked($ticket, function (Ticket $ticket) use ($employee, $actor) {
            $this->assertStatus($ticket, TicketStatus::Waiting);
            $ticket->forceFill(['assigned_employee_id' => $employee?->id])->save();
            $this->record($ticket, 'assigned', TicketStatus::Waiting, TicketStatus::Waiting, $actor, employeeId: $employee?->id);

            return $ticket;
        });
    }

    /**
     * Move a ticket to another department and/or employee. It returns to
     * Waiting in the target queue; its check-in time is kept for reporting.
     */
    public function transfer(Ticket $ticket, ?Department $department, ?Employee $employee, Actor $actor, ?string $note = null): Ticket
    {
        return $this->locked($ticket, function (Ticket $ticket) use ($department, $employee, $actor, $note) {
            if (! in_array($ticket->status, [TicketStatus::Waiting, TicketStatus::Called, TicketStatus::InService], true)) {
                throw QueueActionException::invalidTransition($ticket, TicketStatus::Waiting);
            }
            if ($department === null && $employee === null) {
                throw new QueueActionException(__('Choose a department or an employee to transfer to.'));
            }
            if ($department !== null && $department->location_id !== $ticket->location_id) {
                throw new QueueActionException(__('The department must be at the same location.'));
            }

            $now = now();
            $fromDepartment = $ticket->department_id;
            $employeeId = $ticket->serving_employee_id;
            $keepPosition = (bool) $ticket->location->tenant->settings()->get('transfer_keeps_queue_position');

            $changes = [
                'department_id' => $department === null ? $ticket->department_id : $department->id,
                'assigned_employee_id' => $employee?->id,
                'serving_employee_id' => null,
                'desk_id' => null,
                'called_at' => null,
                'transfer_count' => $ticket->transfer_count + 1,
                'queued_at' => $keepPosition ? $ticket->queued_at : $now,
            ];
            if ($ticket->status === TicketStatus::InService) {
                $changes['service_seconds'] = $this->serviceSeconds($ticket, $now);
                $changes['service_started_at'] = null;
            }

            $this->transition($ticket, TicketStatus::Waiting, 'transferred', $actor, $changes,
                meta: array_filter(['note' => $note, 'to_employee_id' => $employee?->id]),
                fromDepartmentId: $fromDepartment, toDepartmentId: $changes['department_id']);

            if ($note && $actor->type === 'user') {
                $ticket->notes()->create(['author_id' => $actor->id, 'body' => $note]);
            }
            $this->releaseEmployee($employeeId);

            return $ticket;
        });
    }

    public function noShow(Ticket $ticket, Actor $actor): Ticket
    {
        return $this->locked($ticket, function (Ticket $ticket) use ($actor) {
            $employeeId = $ticket->serving_employee_id;
            $this->transition($ticket, TicketStatus::NoShow, 'no_show', $actor, ['completed_at' => now()]);
            $ticket->customer?->increment('no_show_count');
            $this->releaseEmployee($employeeId);

            return $ticket;
        });
    }

    public function cancelByCustomer(Ticket $ticket): Ticket
    {
        return $this->locked($ticket, fn (Ticket $ticket) => $this->transition($ticket, TicketStatus::Cancelled, 'cancelled', Actor::customer(), ['completed_at' => now()]));
    }

    /** End-of-day closeout of tickets still waiting or held. */
    public function closeUnserved(Ticket $ticket): Ticket
    {
        return $this->locked($ticket, function (Ticket $ticket) {
            $now = now();
            $held = $ticket->hold_started_at ? (int) $ticket->hold_started_at->diffInSeconds($now) : 0;

            return $this->transition($ticket, TicketStatus::ClosedUnserved, 'closed', Actor::system(), [
                'completed_at' => $now,
                'closed_reason' => 'end_of_day',
                'hold_started_at' => null,
                'total_hold_seconds' => $ticket->total_hold_seconds + $held,
            ]);
        });
    }

    // ---------------------------------------------------------------------

    /**
     * Re-read the ticket under the location lock, then run the change.
     *
     * @template T
     *
     * @param  \Closure(Ticket): T  $callback
     * @return T
     */
    private function locked(Ticket $ticket, \Closure $callback): mixed
    {
        $location = $ticket->location;

        return $this->lock->run($location, function () use ($ticket, $callback) {
            $fresh = Ticket::query()->with('location.tenant')->findOrFail($ticket->id);
            $result = $callback($fresh);
            $ticket->setRawAttributes($fresh->getAttributes(), true);

            return $result;
        });
    }

    private function doCall(Ticket $ticket, Employee $employee, Actor $actor, string $type): Ticket
    {
        $now = now();
        $changes = [
            'serving_employee_id' => $employee->id,
            'desk_id' => $employee->current_desk_id,
            'called_at' => $now,
            'assigned_employee_id' => null,
        ];
        if ($ticket->first_called_at === null) {
            $changes['first_called_at'] = $now;
            $changes['wait_seconds'] = max(0, (int) $ticket->checked_in_at->diffInSeconds($now) - $ticket->total_hold_seconds);
        }

        $this->transition($ticket, TicketStatus::Called, $type, $actor, $changes);
        $this->setEmployeeStatus($employee, EmployeeStatus::Busy);

        return $ticket;
    }

    /**
     * @param  array<string, mixed>  $changes
     * @param  array<string, mixed>  $meta
     */
    private function transition(
        Ticket $ticket,
        TicketStatus $to,
        string $type,
        Actor $actor,
        array $changes = [],
        array $meta = [],
        ?int $fromDepartmentId = null,
        ?int $toDepartmentId = null,
    ): Ticket {
        $from = $ticket->status;
        if (! $from->canTransitionTo($to)) {
            throw QueueActionException::invalidTransition($ticket, $to);
        }

        $ticket->forceFill($changes + ['status' => $to])->save();
        $this->record($ticket, $type, $from, $to, $actor, $meta, $ticket->serving_employee_id, $ticket->desk_id, $fromDepartmentId, $toDepartmentId);

        return $ticket;
    }

    /** @param  array<string, mixed>  $meta */
    private function record(
        Ticket $ticket,
        string $type,
        ?TicketStatus $from,
        ?TicketStatus $to,
        Actor $actor,
        array $meta = [],
        ?int $employeeId = null,
        ?int $deskId = null,
        ?int $fromDepartmentId = null,
        ?int $toDepartmentId = null,
    ): void {
        TicketEvent::create([
            'ticket_id' => $ticket->id,
            'type' => $type,
            'from_status' => $from?->value,
            'to_status' => $to?->value,
            'actor_type' => $actor->type,
            'actor_id' => $actor->id,
            'employee_id' => $employeeId,
            'desk_id' => $deskId,
            'from_department_id' => $fromDepartmentId,
            'to_department_id' => $toDepartmentId,
            'meta' => $meta ?: null,
            'created_at' => now(),
        ]);

        $this->audit->log('ticket.'.$type, $ticket,
            before: $from ? ['status' => $from->value] : null,
            after: array_filter(['status' => $to?->value, 'from_department_id' => $fromDepartmentId, 'to_department_id' => $toDepartmentId] + $meta, fn ($v) => $v !== null),
            locationId: $ticket->location_id,
            actor: ['type' => $actor->type, 'id' => $actor->id, 'name' => $actor->name]);

        $this->lock->changed($ticket->location_id, $type, $ticket->id);
    }

    private function assertStatus(Ticket $ticket, TicketStatus $expected): void
    {
        if ($ticket->status !== $expected) {
            throw QueueActionException::invalidTransition($ticket, $expected);
        }
    }

    private function assertCanTakeTicket(Employee $employee, Location $location): void
    {
        if ($employee->current_location_id !== $location->id || $employee->status === EmployeeStatus::Offline || $employee->status === EmployeeStatus::OnBreak) {
            throw new QueueActionException(__('Start your shift at this location to call customers.'));
        }

        $serving = Ticket::query()
            ->where('serving_employee_id', $employee->id)
            ->whereIn('status', [TicketStatus::Called->value, TicketStatus::InService->value])
            ->exists();
        if ($serving) {
            throw new QueueActionException(__('Finish your current customer first.'));
        }
    }

    private function serviceSeconds(Ticket $ticket, Carbon $now): ?int
    {
        if ($ticket->service_started_at === null) {
            return $ticket->service_seconds;
        }

        return (int) ($ticket->service_seconds ?? 0) + (int) $ticket->service_started_at->diffInSeconds($now);
    }

    /** After a ticket leaves an employee, a Busy employee with nothing else in hand becomes Available. */
    private function releaseEmployee(?int $employeeId): void
    {
        if ($employeeId === null) {
            return;
        }

        $employee = Employee::query()->find($employeeId);
        if ($employee === null || $employee->status !== EmployeeStatus::Busy) {
            return;
        }

        $stillServing = Ticket::query()->where('serving_employee_id', $employeeId)
            ->whereIn('status', [TicketStatus::Called->value, TicketStatus::InService->value])->exists();

        if (! $stillServing) {
            $this->setEmployeeStatus($employee, EmployeeStatus::Available);
        }
    }

    private function setEmployeeStatus(Employee $employee, EmployeeStatus $status): void
    {
        if ($employee->status === $status) {
            return;
        }

        $from = $employee->status;
        $employee->forceFill(['status' => $status, 'status_changed_at' => now()])->save();
        EmployeeStatusChanged::dispatch($employee, $from, $status);
    }
}
