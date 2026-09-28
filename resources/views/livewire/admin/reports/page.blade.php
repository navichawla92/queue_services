@php
    $fmt = fn ($v, $suffix = '') => $v === null ? '—' : $v.$suffix;
    $tiles = [
        ['served', __('Customers served'), $fmt($summary['served'])],
        ['avg_wait', __('Average wait'), $fmt($summary['avg_wait_min'], ' min')],
        ['avg_service', __('Average service time'), $fmt($summary['avg_service_min'], ' min')],
        ['no_show_rate', __('No-show rate'), $fmt($summary['no_show_rate'], '%')],
        ['abandon_rate', __('Abandoned queue rate'), $fmt($summary['abandon_rate'], '%')],
        ['appt_share', __('Appointments vs walk-ins'), $summary['appointments'].' / '.$summary['walk_ins']],
        ['appt_no_show_rate', __('Appointment no-show rate'), $fmt($summary['appt_no_show_rate'], '%')],
        ['satisfaction', __('Satisfaction'), $summary['satisfaction'] === null ? '—' : $summary['satisfaction'].'★ ('.$summary['feedback_count'].')'],
    ];
    $peakMax = max(1, max(array_map('max', $peak)));
    $dayNames = [__('Sun'), __('Mon'), __('Tue'), __('Wed'), __('Thu'), __('Fri'), __('Sat')];
@endphp
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold">{{ __('Reports') }}</h1>
        @can('reports.export')
            <div class="flex gap-2 text-sm">
                @foreach (['summary' => __('Summary'), 'trend' => __('Trend'), 'employee' => __('By employee'), 'department' => __('By department'), 'service' => __('By service'), 'location' => __('By location')] as $type => $label)
                    <a href="{{ route('admin.reports.export', $exportQuery + ['type' => $type, 'grain' => $grainValue]) }}" class="rounded border border-slate-300 px-2 py-1">CSV: {{ $label }}</a>
                @endforeach
            </div>
        @endcan
    </div>

    @include('livewire.admin.reports.partials.filters')

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" data-testid="kpi-tiles">
        @foreach ($tiles as [$key, $label, $value])
            <div class="rounded-lg bg-white p-4 shadow-sm" title="{{ $definitions[$key] ?? '' }}">
                <div class="flex items-center gap-1 text-sm text-slate-500">{{ $label }} @if (isset($definitions[$key])) <span class="cursor-help rounded-full border px-1 text-xs" aria-label="{{ $definitions[$key] }}">i</span> @endif</div>
                <div class="text-2xl font-semibold" data-testid="kpi-{{ $key }}">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    <section class="rounded-lg bg-white p-4 shadow-sm">
        <div class="mb-2 flex items-center justify-between">
            <h2 class="font-semibold">{{ __('Volume and times') }}</h2>
            <select wire:model.live="grain" class="rounded border border-slate-300 px-2 py-1 text-sm" aria-label="{{ __('Group by') }}">
                <option value="day">{{ __('Daily') }}</option><option value="week">{{ __('Weekly') }}</option><option value="month">{{ __('Monthly') }}</option>
            </select>
        </div>
        <div wire:key="trend-{{ md5(json_encode($trend)) }}" x-data="trendChart(@js($trend))" class="h-64">
            <canvas x-ref="canvas" aria-label="{{ __('Trend chart') }}"></canvas>
        </div>
        <details class="mt-2 text-sm"><summary class="cursor-pointer text-slate-500">{{ __('Data table') }}</summary>
            <table class="mt-2 min-w-full"><thead class="text-left text-slate-500"><tr><th>{{ __('Period') }}</th><th>{{ __('Check-ins') }}</th><th>{{ __('Served') }}</th><th>{{ __('Avg wait') }}</th><th>{{ __('Avg service') }}</th></tr></thead>
                <tbody>@foreach ($trend as $r) <tr><td>{{ $r['period'] }}</td><td>{{ $r['tickets'] }}</td><td>{{ $r['served'] }}</td><td>{{ $fmt($r['avg_wait_min']) }}</td><td>{{ $fmt($r['avg_service_min']) }}</td></tr> @endforeach</tbody>
            </table>
        </details>
    </section>

    <section class="overflow-x-auto rounded-lg bg-white p-4 shadow-sm">
        <h2 class="mb-2 font-semibold">{{ __('Peak hours (check-ins)') }}</h2>
        <table class="text-xs" data-testid="peak-heatmap">
            <thead><tr><th></th>@foreach (range(0, 23) as $h) <th class="w-7 font-normal text-slate-500">{{ $h }}</th> @endforeach</tr></thead>
            <tbody>
                @foreach ([1, 2, 3, 4, 5, 6, 0] as $d)
                    <tr>
                        <th class="pr-2 text-left font-normal text-slate-500">{{ $dayNames[$d] }}</th>
                        @foreach (range(0, 23) as $h)
                            @php $n = $peak[$d][$h]; @endphp
                            <td class="h-6 w-7 text-center" style="background: rgba(37, 99, 235, {{ round($n / $peakMax, 2) }}); color: {{ $n / $peakMax > .5 ? '#fff' : '#334155' }}" title="{{ $dayNames[$d] }} {{ $h }}:00 — {{ $n }}">{{ $n ?: '' }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    <div class="grid gap-4 lg:grid-cols-2">
        @foreach (['employee' => __('By employee'), 'department' => __('By department'), 'service' => __('By service'), 'location' => __('By location')] as $by => $title)
            <section class="overflow-x-auto rounded-lg bg-white p-4 shadow-sm" data-testid="breakdown-{{ $by }}">
                <h2 class="mb-2 font-semibold">{{ $title }}</h2>
                <table class="min-w-full text-sm">
                    <thead class="text-left text-slate-500"><tr><th>{{ __('Name') }}</th><th>{{ __('Served') }}</th><th>{{ __('Avg wait') }}</th><th>{{ __('Avg service') }}</th><th>{{ __('No-show') }}</th><th>★</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($breakdowns[$by] as $r)
                            <tr><td class="py-1">{{ $r['name'] }}</td><td>{{ $r['served'] }}</td><td>{{ $fmt($r['avg_wait_min']) }}</td><td>{{ $fmt($r['avg_service_min']) }}</td><td>{{ $fmt($r['no_show_rate'], '%') }}</td><td>{{ $fmt($r['satisfaction']) }}</td></tr>
                        @empty
                            <tr><td colspan="6" class="py-3 text-center text-slate-400">{{ __('No data for these filters.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        @endforeach
    </div>

    @assets
        @vite('resources/js/charts.js')
    @endassets
</div>
