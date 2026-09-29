<div wire:poll.10s class="space-y-6" data-testid="operations">
    <h1 class="page-title">{{ __('Live operations') }} <span class="text-base font-normal text-slate-500">· {{ $location->name }}</span></h1>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        @foreach ([
            [__('Waiting now'), $waitingCount],
            [__('Longest wait'), $longestWaitMin.' min'],
            [__('Average wait today'), $avgWaitTodayMin === null ? '—' : $avgWaitTodayMin.' min'],
            [__('Being served'), $inService],
            [__('Served today'), $servedToday],
        ] as [$label, $value])
            <div class="card p-4"><div class="text-sm text-slate-500">{{ $label }}</div><div class="text-2xl font-semibold">{{ $value }}</div></div>
        @endforeach
    </div>

    <section class="card p-4">
        <h2 class="mb-2 font-semibold">{{ __('Waiting longer than :min min', ['min' => $slaMinutes]) }} <span class="text-slate-400">({{ $breaches->count() }})</span></h2>
        <ul class="space-y-1 text-sm" data-testid="sla-breaches">
            @forelse ($breaches as $t)
                <li class="rounded bg-red-50 px-3 py-1 text-red-800"><strong>{{ $t->number }}</strong> · {{ $t->service->name }} · {{ $t->department->name }} · {{ intdiv($t->currentWaitSeconds(), 60) }} min</li>
            @empty
                <li class="text-slate-400">{{ __('Nobody is over the limit.') }}</li>
            @endforelse
        </ul>
    </section>

    <section class="card p-4">
        <h2 class="mb-2 font-semibold">{{ __('Staff') }} <span class="text-sm font-normal text-slate-500">· {{ trans_choice(':count assigned person offline|:count assigned people offline', $offline, ['count' => $offline]) }}</span></h2>
        <div class="flex flex-wrap gap-2 text-sm">
            @forelse ($staff as $s)
                <span @class(['rounded px-3 py-1', 'bg-green-100' => $s->status->value === 'available', 'bg-blue-100' => $s->status->value === 'busy', 'bg-amber-100' => $s->status->value === 'on_break'])>
                    {{ $s->display_name }} · {{ $s->status->label() }}{{ $s->currentDesk ? ' · '.$s->currentDesk->label : '' }}
                </span>
            @empty
                <span class="text-slate-400">{{ __('No one is on shift.') }}</span>
            @endforelse
        </div>
    </section>
</div>
