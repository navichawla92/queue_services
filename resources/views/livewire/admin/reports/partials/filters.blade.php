<div class="flex flex-wrap items-center gap-3 rounded-lg bg-white p-3 text-sm shadow-sm" data-testid="report-filters">
    <label>{{ __('From') }} <input type="date" wire:model.live="from" class="rounded border border-slate-300 px-2 py-1"></label>
    <label>{{ __('To') }} <input type="date" wire:model.live="to" class="rounded border border-slate-300 px-2 py-1"></label>
    <div class="flex gap-1">
        @foreach (['today' => __('Today'), '7d' => '7d', '30d' => '30d', 'month' => __('This month'), 'quarter' => __('Quarter'), '12m' => '12m'] as $p => $label)
            <button type="button" wire:click="range('{{ $p }}')" class="rounded border border-slate-300 px-2 py-1">{{ $label }}</button>
        @endforeach
    </div>
    <select wire:model.live="location" class="rounded border border-slate-300 px-2 py-1" aria-label="{{ __('Location') }}">
        @if ($locations->count() > 1) <option value="">{{ __('All my locations') }}</option> @endif
        @foreach ($locations as $l) <option value="{{ $l->id }}">{{ $l->name }}</option> @endforeach
    </select>
    @isset($departments)
        <select wire:model.live="department" class="rounded border border-slate-300 px-2 py-1" aria-label="{{ __('Department') }}">
            <option value="">{{ __('All departments') }}</option>
            @foreach ($departments as $d) <option value="{{ $d->id }}">{{ $d->name }}</option> @endforeach
        </select>
        <select wire:model.live="service" class="rounded border border-slate-300 px-2 py-1" aria-label="{{ __('Service') }}">
            <option value="">{{ __('All services') }}</option>
            @foreach ($services as $s) <option value="{{ $s->id }}">{{ $s->name }}</option> @endforeach
        </select>
        <select wire:model.live="employee" class="rounded border border-slate-300 px-2 py-1" aria-label="{{ __('Employee') }}">
            <option value="">{{ __('All employees') }}</option>
            @foreach ($employees as $e) <option value="{{ $e->id }}">{{ $e->display_name }}</option> @endforeach
        </select>
    @endisset
</div>
