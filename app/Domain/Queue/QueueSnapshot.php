<?php

namespace App\Domain\Queue;

use App\Domain\Organization\EmployeeStatus;
use App\Domain\Organization\Models\Employee;
use App\Domain\Organization\Models\Location;
use App\Domain\Queue\Models\LocationQueueState;
use App\Domain\Queue\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Full current state of a location's live queue: the single source of truth
 * clients (re)load on first render, on a version gap, and after reconnecting
 * (design Decision 5).
 */
class QueueSnapshot
{
    public function __construct(
        private readonly QueueOrdering $ordering,
        private readonly QueuePositions $positions,
    ) {}

    public function version(Location $location): int
    {
        return (int) LocationQueueState::query()->whereKey($location->id)->value('version');
    }

    /** @return Collection<int, Ticket> active tickets, waiting ones in queue order first */
    public function activeTickets(Location $location, ?\Closure $filter = null): Collection
    {
        $query = Ticket::query()
            ->with(['service', 'department', 'assignedEmployee', 'servingEmployee', 'desk'])
            ->withCount('notes')
            ->where('location_id', $location->id)
            ->active()
            ->when($filter, fn (Builder $q) => $filter($q));

        $query->orderByRaw("FIELD(status, 'called', 'in_service', 'waiting', 'on_hold')");

        return $this->ordering->ordered($query)->get();
    }

    /** @return Collection<int, Employee> employees currently on shift here */
    public function staff(Location $location): Collection
    {
        return Employee::query()->with('currentDesk')
            ->where('current_location_id', $location->id)
            ->where('status', '!=', EmployeeStatus::Offline->value)
            ->orderBy('display_name')->get();
    }

    /** @return array<string, mixed> JSON-ready snapshot for API clients */
    public function toArray(Location $location): array
    {
        $tickets = $this->activeTickets($location);
        $positions = $this->positions->estimateMany($location);

        return [
            'version' => $this->version($location),
            'location' => ['id' => $location->id, 'name' => $location->name],
            'generated_at' => now()->toIso8601String(),
            'tickets' => $tickets->map(fn (Ticket $t) => [
                'id' => $t->id,
                'number' => $t->number,
                'customer_name' => $t->customer_name,
                'service' => $t->service->name,
                'department' => ['id' => $t->department_id, 'name' => $t->department->name, 'color' => $t->department->color],
                'status' => $t->status->value,
                'customer_type' => $t->customer_type->value,
                'checked_in_at' => $t->checked_in_at->toIso8601String(),
                'wait_seconds' => $t->currentWaitSeconds(),
                'priority' => $t->priority,
                'assigned_employee' => $t->assignedEmployee?->display_name,
                'serving_employee' => $t->servingEmployee?->display_name,
                'desk' => $t->desk?->label,
                'notes_count' => $t->notes_count,
                'position' => $positions[$t->id]['position'] ?? null,
            ])->values()->all(),
            'staff' => $this->staff($location)->map(fn (Employee $e) => [
                'id' => $e->id,
                'name' => $e->display_name,
                'status' => $e->status->value,
                'desk' => $e->currentDesk?->label,
            ])->values()->all(),
        ];
    }
}
