@php $fmt = fn ($v, $s = '') => $v === null ? '—' : $v.$s; @endphp
<div class="space-y-6" data-testid="overview">
    <h1 class="text-2xl font-semibold">{{ __('All locations') }}</h1>
    @include('livewire.admin.reports.partials.filters')

    <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-slate-500">
                <tr>
                    <th class="px-3 py-2">{{ __('Location') }}</th>
                    <th class="px-3 py-2">{{ __('Waiting now') }}</th>
                    <th class="px-3 py-2">{{ __('Longest wait') }}</th>
                    <th class="px-3 py-2">{{ __('Staff on shift') }}</th>
                    <th class="px-3 py-2">{{ __('Served') }}</th>
                    <th class="px-3 py-2">{{ __('Avg wait') }}</th>
                    <th class="px-3 py-2">{{ __('Avg service') }}</th>
                    <th class="px-3 py-2">{{ __('No-show') }}</th>
                    <th class="px-3 py-2">★</th>
                    <th></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($rows as $r)
                    <tr data-testid="overview-{{ $r['location']->id }}">
                        <td class="px-3 py-2 font-medium">{{ $r['location']->name }}</td>
                        <td class="px-3 py-2">{{ $r['waiting'] }}</td>
                        <td class="px-3 py-2">{{ $r['longest'] }} min</td>
                        <td class="px-3 py-2">{{ $r['staff'] }}</td>
                        <td class="px-3 py-2">{{ $r['kpi']['served'] }}</td>
                        <td class="px-3 py-2">{{ $fmt($r['kpi']['avg_wait_min'], ' min') }}</td>
                        <td class="px-3 py-2">{{ $fmt($r['kpi']['avg_service_min'], ' min') }}</td>
                        <td class="px-3 py-2">{{ $fmt($r['kpi']['no_show_rate'], '%') }}</td>
                        <td class="px-3 py-2">{{ $fmt($r['kpi']['satisfaction']) }}</td>
                        <td class="whitespace-nowrap px-3 py-2 text-right">
                            <a href="{{ route('admin.reports', ['location' => $r['location']->id, 'from' => $from, 'to' => $to]) }}" class="underline">{{ __('Details') }}</a>
                        </td>
                    </tr>
                @endforeach
                <tr class="bg-slate-50 font-semibold">
                    <td class="px-3 py-2">{{ __('Total') }}</td>
                    <td class="px-3 py-2">{{ $rows->sum('waiting') }}</td><td></td><td class="px-3 py-2">{{ $rows->sum('staff') }}</td>
                    <td class="px-3 py-2">{{ $total['served'] }}</td>
                    <td class="px-3 py-2">{{ $fmt($total['avg_wait_min'], ' min') }}</td>
                    <td class="px-3 py-2">{{ $fmt($total['avg_service_min'], ' min') }}</td>
                    <td class="px-3 py-2">{{ $fmt($total['no_show_rate'], '%') }}</td>
                    <td class="px-3 py-2">{{ $fmt($total['satisfaction']) }}</td><td></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
