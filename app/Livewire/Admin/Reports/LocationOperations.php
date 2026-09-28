<?php

namespace App\Livewire\Admin\Reports;

use App\Domain\Access\CurrentLocation;
use App\Domain\Organization\EmployeeStatus;
use App\Domain\Organization\Models\Employee;
use App\Domain\Queue\Models\Ticket;
use App\Domain\Queue\TicketStatus;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Live location operations dashboard (analytics-reporting "Location
 * operations dashboard"): refreshes every 10 s.
 */
#[Layout('layouts.app')]
class LocationOperations extends Component
{
    public function mount(): void
    {
        Gate::authorize('reports.view');
    }

    public function render(CurrentLocation $current)
    {
        $location = $current->require();
        $tz = $location->effectiveTimezone();
        $slaMinutes = (int) app(TenantContext::class)->require()->settings()->get('sla_wait_minutes', 20);
        $today = now($tz)->toDateString();

        $waiting = Ticket::query()->with('department', 'service')
            ->where('location_id', $location->id)->where('status', TicketStatus::Waiting->value)
            ->orderBy('checked_in_at')->get();
        $waits = $waiting->map(fn (Ticket $t) => $t->currentWaitSeconds());

        $todayTickets = Ticket::query()->where('location_id', $location->id)->whereDate('local_date', $today);

        return view('livewire.admin.reports.operations', [
            'location' => $location,
            'waitingCount' => $waiting->count(),
            'longestWaitMin' => $waits->isEmpty() ? 0 : intdiv((int) $waits->max(), 60),
            'avgWaitTodayMin' => ($avg = (clone $todayTickets)->whereNotNull('wait_seconds')->avg('wait_seconds')) !== null ? round($avg / 60, 1) : null,
            'servedToday' => (clone $todayTickets)->where('status', TicketStatus::Completed->value)->count(),
            'inService' => (clone $todayTickets)->whereIn('status', [TicketStatus::Called->value, TicketStatus::InService->value])->count(),
            'breaches' => $waiting->filter(fn (Ticket $t) => $t->currentWaitSeconds() > $slaMinutes * 60)->values(),
            'slaMinutes' => $slaMinutes,
            'staff' => Employee::query()->with('currentDesk')->where('current_location_id', $location->id)
                ->where('status', '!=', EmployeeStatus::Offline->value)->orderBy('display_name')->get(),
            'offline' => Employee::query()->whereHas('user', fn ($q) => $q->where('is_active', true)->whereHas('locations', fn ($l) => $l->whereKey($location->id)))
                ->where(fn ($q) => $q->where('status', EmployeeStatus::Offline->value)->orWhere('current_location_id', '!=', $location->id)->orWhereNull('current_location_id'))
                ->count(),
        ]);
    }
}
