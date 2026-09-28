<?php

namespace App\Livewire\Admin\Reports;

use App\Domain\Access\LocationAccess;
use App\Domain\Analytics\Kpis;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Employee;
use App\Domain\Organization\Models\Location;
use App\Domain\Organization\Models\Service;
use App\Livewire\Admin\Reports\Concerns\HasReportFilters;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Management reports (analytics-reporting): KPI tiles with definitions,
 * volume / wait / satisfaction trends, peak-hours heatmap and breakdowns by
 * employee, department, service and location. Permission: reports.view.
 */
#[Layout('layouts.app')]
class ReportsPage extends Component
{
    use HasReportFilters;

    #[Url]
    public string $grain = 'day';

    public function mount(): void
    {
        Gate::authorize('reports.view');
    }

    public function render(Kpis $kpis, LocationAccess $access)
    {
        Gate::authorize('reports.view');
        $filter = $this->filter();
        $grain = in_array($this->grain, ['day', 'week', 'month'], true) ? $this->grain : 'day';

        $names = [
            'employee' => Employee::query()->pluck('display_name', 'id'),
            'department' => Department::query()->pluck('name', 'id'),
            'service' => Service::query()->pluck('name', 'id'),
            'location' => Location::query()->pluck('name', 'id'),
        ];
        $breakdowns = [];
        foreach (array_keys($names) as $by) {
            $satisfaction = $kpis->feedbackBy($filter, $by);
            $breakdowns[$by] = array_map(fn ($row) => $row + [
                'name' => $names[$by][$row['id']] ?? __('Unknown'),
                'satisfaction' => $satisfaction[$row['id']]['avg'] ?? null,
            ], $kpis->breakdown($filter, $by));
        }

        return view('livewire.admin.reports.page', [
            'summary' => $kpis->summary($filter),
            'trend' => $kpis->trend($filter, $grain),
            'peak' => $kpis->peakHours($filter),
            'breakdowns' => $breakdowns,
            'definitions' => Kpis::DEFINITIONS,
            'locations' => $access->accessibleLocations(auth()->user())->get(),
            'departments' => Department::query()->active()->ordered()->get(),
            'services' => Service::query()->orderBy('name')->get(),
            'employees' => Employee::query()->orderBy('display_name')->get(),
            'exportQuery' => $this->filterQuery(),
            'grainValue' => $grain,
        ]);
    }
}
