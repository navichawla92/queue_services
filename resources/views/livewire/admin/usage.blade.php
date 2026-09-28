<div class="max-w-4xl space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">{{ __('Plan & usage') }}</h1>
        <div class="flex items-center gap-2 text-sm">
            <input type="month" wire:model.live="period" class="rounded border border-slate-300 px-2 py-1" aria-label="{{ __('Period') }}">
            <a href="{{ route('admin.usage.export', ['period' => $periodValue]) }}" class="rounded border border-slate-300 px-2 py-1">CSV</a>
        </div>
    </div>

    <div class="rounded-lg bg-white p-5 shadow-sm">
        <div class="text-sm text-slate-500">{{ __('Current plan') }}</div>
        <div class="text-xl font-semibold" data-testid="plan-name">{{ $plan?->name ?? '—' }}</div>
        <div class="mt-3 flex flex-wrap gap-2 text-sm">
            @foreach ($features as $key => $label)
                <span @class(['rounded px-2 py-0.5', 'bg-green-100 text-green-800' => $plan?->hasFeature($key), 'bg-slate-100 text-slate-400 line-through' => ! $plan?->hasFeature($key)])>{{ __($label) }}</span>
            @endforeach
        </div>
    </div>

    <table class="min-w-full rounded-lg bg-white text-sm shadow-sm" data-testid="usage-table">
        <thead class="bg-slate-50 text-left text-slate-500"><tr><th class="px-3 py-2">{{ __('Item') }}</th><th class="px-3 py-2">{{ __('Used') }}</th><th class="px-3 py-2">{{ __('Limit') }}</th><th class="px-3 py-2 w-1/3"></th></tr></thead>
        <tbody class="divide-y divide-slate-100">
            @foreach ($rows as $r)
                <tr>
                    <td class="px-3 py-2">{{ __($r['label']) }}</td>
                    <td class="px-3 py-2">{{ number_format($r['used']) }}</td>
                    <td class="px-3 py-2">{{ $r['limit'] === null ? __('Unlimited') : number_format($r['limit']) }}</td>
                    <td class="px-3 py-2">
                        @if ($r['percent'] !== null)
                            <div class="h-2 rounded bg-slate-100"><div @class(['h-2 rounded', 'bg-green-500' => $r['percent'] < 80, 'bg-amber-500' => $r['percent'] >= 80 && $r['percent'] < 100, 'bg-red-600' => $r['percent'] >= 100]) style="width: {{ min(100, $r['percent']) }}%"></div></div>
                            <span class="text-xs text-slate-500">{{ $r['percent'] }}%</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
