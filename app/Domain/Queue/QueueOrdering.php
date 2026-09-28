<?php

namespace App\Domain\Queue;

use App\Domain\Organization\Models\Employee;
use App\Domain\Queue\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;

/**
 * Queue order (customer-routing "Queue ordering"): priority desc, then time in
 * queue, then id. Held tickets are not waiting, so never offered. Tickets
 * directly assigned to an employee are offered only to that employee, first.
 */
class QueueOrdering
{
    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function ordered(Builder $query): Builder
    {
        return $query->orderByDesc('priority')->orderBy('queued_at')->orderBy('id');
    }

    /** @return Builder<Ticket> waiting tickets this employee may be offered by "call next" */
    public function offerableTo(Employee $employee, int $locationId): Builder
    {
        $departmentIds = $employee->departments()->where('location_id', $locationId)->pluck('departments.id')->all();
        $serviceIds = $employee->services()->pluck('services.id')->all();

        $query = Ticket::query()
            ->where('location_id', $locationId)
            ->where('status', TicketStatus::Waiting->value)
            ->where(fn (Builder $q) => $q
                ->where('assigned_employee_id', $employee->id)
                ->orWhere(fn (Builder $q) => $q
                    ->whereNull('assigned_employee_id')
                    ->whereIn('department_id', $departmentIds)
                    ->whereIn('service_id', $serviceIds)));

        // Direct assignments to me first.
        $query->orderByRaw('CASE WHEN assigned_employee_id = ? THEN 0 ELSE 1 END', [$employee->id]);

        return $this->ordered($query);
    }

    /** Number of waiting tickets ahead of this one in its department. */
    public function ahead(Ticket $ticket): int
    {
        if ($ticket->status !== TicketStatus::Waiting) {
            return 0;
        }

        return Ticket::query()
            ->where('location_id', $ticket->location_id)
            ->where('department_id', $ticket->department_id)
            ->where('status', TicketStatus::Waiting->value)
            ->where('id', '!=', $ticket->id)
            ->where(fn (Builder $q) => $q
                ->where('priority', '>', $ticket->priority)
                ->orWhere(fn (Builder $q) => $q->where('priority', $ticket->priority)->where('queued_at', '<', $ticket->queued_at))
                ->orWhere(fn (Builder $q) => $q->where('priority', $ticket->priority)->where('queued_at', $ticket->queued_at)->where('id', '<', $ticket->id)))
            ->count();
    }
}
