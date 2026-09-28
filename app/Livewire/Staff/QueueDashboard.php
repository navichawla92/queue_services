<?php

namespace App\Livewire\Staff;

use App\Domain\Access\CurrentLocation;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Employee;
use App\Domain\Organization\Models\Location;
use App\Domain\Organization\Models\Service;
use App\Domain\Queue\Actor;
use App\Domain\Queue\Broadcasting\StaffQueueChanged;
use App\Domain\Queue\Exceptions\QueueActionException;
use App\Domain\Queue\Models\Ticket;
use App\Domain\Queue\Notes;
use App\Domain\Queue\QueueSnapshot;
use App\Domain\Queue\TicketStateMachine;
use App\Domain\Queue\TicketStatus;
use App\Domain\Routing\EligibleEmployees;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Real-time staff queue (staff-queue spec). Every action goes through the
 * TicketStateMachine; permission model:
 *  - queue.serve:  call next / eligible tickets, act on tickets I'm serving
 *  - queue.manage: act on any ticket at my location, call for others, assign
 */
#[Layout('layouts.app')]
class QueueDashboard extends Component
{
    #[Url]
    public ?int $filterDepartment = null;

    #[Url]
    public ?int $filterService = null;

    #[Url]
    public ?string $filterStatus = null;

    #[Url]
    public ?int $filterEmployee = null;

    #[Url]
    public ?string $filterType = null;

    #[Url]
    public bool $mine = false;

    public int $version = 0;

    public ?string $flash = null;

    public ?string $error = null;

    // Dialog state
    public ?int $dialogTicketId = null;

    public ?string $dialog = null; // transfer | hold | note | complete | assign | call

    public ?int $targetDepartmentId = null;

    public ?int $targetEmployeeId = null;

    public string $text = '';

    // ---------------------------------------------------------------- actions

    public function callNext(TicketStateMachine $queue): void
    {
        $this->act(function () use ($queue) {
            $me = $this->me() ?? throw new QueueActionException(__('You do not have a serving profile.'));
            abort_unless($this->user()->can('queue.serve'), 403);
            $ticket = $queue->callNext($me, $this->location(), Actor::employee($me));

            return $ticket
                ? __('Called :number (:name).', ['number' => $ticket->number, 'name' => $ticket->customer_name])
                : __('No one is waiting for you right now.');
        });
    }

    public function recall(int $ticketId, TicketStateMachine $queue): void
    {
        $this->simple($ticketId, fn (Ticket $t) => $queue->recall($t, $this->actor()));
    }

    public function requeue(int $ticketId, TicketStateMachine $queue): void
    {
        $this->simple($ticketId, fn (Ticket $t) => $queue->requeue($t, $this->actor()));
    }

    public function start(int $ticketId, TicketStateMachine $queue): void
    {
        $this->simple($ticketId, fn (Ticket $t) => $queue->start($t, $this->actor()));
    }

    public function noShow(int $ticketId, TicketStateMachine $queue): void
    {
        $this->simple($ticketId, fn (Ticket $t) => $queue->noShow($t, $this->actor()));
    }

    public function release(int $ticketId, TicketStateMachine $queue): void
    {
        $this->simple($ticketId, fn (Ticket $t) => $queue->release($t, $this->actor()));
    }

    /** @param  callable(Ticket): mixed  $action */
    private function simple(int $ticketId, callable $action): void
    {
        $this->act(function () use ($ticketId, $action) {
            $action($this->ticketFor($ticketId));

            return null;
        });
    }

    public function openDialog(string $dialog, int $ticketId): void
    {
        abort_unless(in_array($dialog, ['transfer', 'hold', 'note', 'complete', 'assign', 'call'], true), 404);
        $this->ticketFor($ticketId, forDialog: $dialog);
        $this->reset('targetDepartmentId', 'targetEmployeeId', 'text', 'error');
        $this->dialog = $dialog;
        $this->dialogTicketId = $ticketId;
    }

    public function closeDialog(): void
    {
        $this->reset('dialog', 'dialogTicketId', 'targetDepartmentId', 'targetEmployeeId', 'text');
    }

    public function confirmDialog(TicketStateMachine $queue, Notes $notes, EligibleEmployees $eligible): void
    {
        $ticket = $this->ticketFor((int) $this->dialogTicketId, forDialog: $this->dialog);
        $text = trim($this->text);
        $this->validate(['text' => ['nullable', 'string', 'max:1000']]);

        $ok = $this->act(function () use ($ticket, $queue, $notes, $eligible, $text) {
            $employee = $this->targetEmployeeId ? Employee::query()->findOrFail($this->targetEmployeeId) : null;

            switch ($this->dialog) {
                case 'transfer':
                    $department = $this->targetDepartmentId
                        ? Department::query()->where('location_id', $ticket->location_id)->findOrFail($this->targetDepartmentId)
                        : null;
                    $queue->transfer($ticket, $department, $employee, $this->actor(), $text ?: null);

                    return __(':number transferred.', ['number' => $ticket->number]);
                case 'hold':
                    $queue->hold($ticket, $this->actor(), $text ?: null);

                    return __(':number placed on hold.', ['number' => $ticket->number]);
                case 'note':
                    if ($text === '') {
                        throw new QueueActionException(__('Write a note first.'));
                    }
                    $notes->addToTicket($ticket, $this->user(), $text);

                    return __('Note added.');
                case 'complete':
                    $queue->complete($ticket, $this->actor(), $text ?: null);

                    return __(':number completed.', ['number' => $ticket->number]);
                case 'assign':
                    $queue->assign($ticket, $employee, $this->actor());

                    return $employee ? __(':number assigned to :name.', ['number' => $ticket->number, 'name' => $employee->display_name]) : __('Assignment removed.');
                case 'call':
                    $employee ??= $this->me() ?? throw new QueueActionException(__('Choose who serves this customer.'));
                    if (! $this->user()->can('queue.manage') && ! $eligible->isEligible($employee, $this->location(), $ticket->department_id, $ticket->service_id)) {
                        abort(403);
                    }
                    $queue->call($ticket, $employee, $this->actor());

                    return __('Called :number to :name.', ['number' => $ticket->number, 'name' => $employee->display_name]);
            }

            return null;
        });

        if ($ok) {
            $this->closeDialog();
        }
    }

    // ---------------------------------------------------------------- helpers

    /** Run a queue action, turning domain errors into a message. */
    private function act(callable $action): bool
    {
        $this->reset('flash', 'error');

        try {
            $message = $action();
            $this->flash = is_string($message) ? $message : null;

            return true;
        } catch (QueueActionException $e) {
            $this->error = $e->getMessage();

            return false;
        }
    }

    /** Load a ticket at my location and check I may act on it. */
    private function ticketFor(int $ticketId, ?string $forDialog = null): Ticket
    {
        $ticket = Ticket::query()->with('location.tenant')->where('location_id', $this->location()->id)->findOrFail($ticketId);
        $user = $this->user();

        if ($user->can('queue.manage')) {
            return $ticket;
        }

        $me = $this->me();
        $mineNow = $me && $ticket->serving_employee_id === $me->id;
        $callable = $forDialog === 'call' && $me && $ticket->status === TicketStatus::Waiting;
        $noteOnly = $forDialog === 'note';

        abort_unless($user->can('queue.serve') && ($mineNow || $callable || $noteOnly), 403);

        return $ticket;
    }

    private function actor(): Actor
    {
        return Actor::user($this->user());
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    private function me(): ?Employee
    {
        return Employee::query()->with('currentDesk')->where('user_id', $this->user()->id)->first();
    }

    private function location(): Location
    {
        return app(CurrentLocation::class)->require();
    }

    public function render(QueueSnapshot $snapshot)
    {
        $location = $this->location();
        $me = $this->me();
        $this->version = $snapshot->version($location);

        $tickets = $snapshot->activeTickets($location, function (Builder $q) use ($me) {
            $q->when($this->filterDepartment, fn ($q, $v) => $q->where('department_id', $v))
                ->when($this->filterService, fn ($q, $v) => $q->where('service_id', $v))
                ->when($this->filterStatus, fn ($q, $v) => $q->where('status', $v))
                ->when($this->filterType, fn ($q, $v) => $q->where('customer_type', $v))
                ->when($this->filterEmployee, fn ($q, $v) => $q->where(fn ($q) => $q->where('assigned_employee_id', $v)->orWhere('serving_employee_id', $v)));

            if ($this->mine && $me) {
                $departmentIds = $me->departments()->pluck('departments.id');
                $serviceIds = $me->services()->pluck('services.id');
                $q->where(fn ($q) => $q->where('serving_employee_id', $me->id)
                    ->orWhere('assigned_employee_id', $me->id)
                    ->orWhere(fn ($q) => $q->whereNull('assigned_employee_id')->where('status', TicketStatus::Waiting->value)
                        ->whereIn('department_id', $departmentIds)->whereIn('service_id', $serviceIds)));
            }
        });

        return view('livewire.staff.queue-dashboard', [
            'location' => $location,
            'timezone' => $location->effectiveTimezone(),
            'me' => $me,
            'serving' => $tickets->filter(fn (Ticket $t) => in_array($t->status, [TicketStatus::Called, TicketStatus::InService], true)),
            'waiting' => $tickets->filter(fn (Ticket $t) => $t->status === TicketStatus::Waiting),
            'held' => $tickets->filter(fn (Ticket $t) => $t->status === TicketStatus::OnHold),
            'staff' => $snapshot->staff($location),
            'departments' => Department::query()->where('location_id', $location->id)->active()->ordered()->get(),
            'services' => Service::query()->offeredAt($location)->get(),
            'employees' => Employee::query()->whereHas('user', fn ($q) => $q->where('is_active', true)->where(fn ($q) => $q->where('all_locations', true)->orWhereHas('locations', fn ($l) => $l->whereKey($location->id))))->orderBy('display_name')->get(),
            'dialogTicket' => $this->dialogTicketId ? Ticket::query()->with('notes.author')->find($this->dialogTicketId) : null,
            'channel' => StaffQueueChanged::channelName($location->tenant_id, $location->id),
            'canManage' => $this->user()->can('queue.manage'),
            'canServe' => $this->user()->can('queue.serve'),
        ]);
    }
}
