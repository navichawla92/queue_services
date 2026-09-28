<?php

namespace App\Livewire\Admin\Reports;

use App\Domain\Access\LocationAccess;
use App\Domain\Analytics\Kpis;
use App\Domain\Analytics\ReportFilter;
use App\Domain\Organization\EmployeeStatus;
use App\Domain\Organization\Models\Employee;
use App\Domain\Organization\Models\Location;
use App\Domain\Queue\Models\Ticket;
use App\Domain\Queue\TicketStatus;
use App\Livewire\Admin\Reports\Concerns\HasReportFilters;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Multi-location management dashboard (analytics-reporting "Multi-location
 * management dashboard"): live queue status and period KPIs side by side,
 * with drill-down.
 */
#[Layout('layouts.app')]
class ManagementOverview extends Component
{
    use HasReportFilters;

    public function mount(): void
    {
        Gate::authorize('reports.view');
    }

    public function render(Kpis $kpis, LocationAccess $access)
    {
        $locations = $access->accessibleLocations(auth()->user())->get();
        $filter = $this->filter();

        $waiting = Ticket::query()->whereIn('location_id', $locations->pluck('id'))->where('status', TicketStatus::Waiting->value)
            ->get(['location_id', 'checked_in_at', 'total_hold_seconds', 'hold_started_at', 'first_called_at'])->groupBy('location_id');
        $onShift = Employee::query()->whereIn('current_location_id', $locations->pluck('id'))
            ->where('status', '!=', EmployeeStatus::Offline->value)->selectRaw('current_location_id, COUNT(*) as n')
            ->groupBy('current_location_id')->pluck('n', 'current_location_id');

        $rows = $locations->map(function (Location $loc) use ($kpis, $filter, $waiting, $onShift) {
            $one = new ReportFilter($filter->from, $filter->to, [$loc->id]);
            $waits = ($waiting[$loc->id] ?? collect())->map(fn (Ticket $t) => $t->currentWaitSeconds());

            return [
                'location' => $loc,
                'waiting' => $waits->count(),
                'longest' => $waits->isEmpty() ? 0 : intdiv((int) $waits->max(), 60),
                'staff' => (int) ($onShift[$loc->id] ?? 0),
                'kpi' => $kpis->summary($one),
            ];
        });

        return view('livewire.admin.reports.overview', [
            'rows' => $rows,
            'total' => $kpis->summary($filter),
            'locations' => $locations,
        ]);
    }
}
