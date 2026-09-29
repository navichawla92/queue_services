<div class="flex flex-wrap items-center gap-3 card p-3 text-sm" data-testid="report-filters">
    <label>{{ __('From') }} <input type="date" wire:model.live="from" class="input input-sm"></label>
    <label>{{ __('To') }} <input type="date" wire:model.live="to" class="input input-sm"></label>
    <div class="flex gap-1">
        @foreach (['today' => __('Today'), '7d' => '7d', '30d' => '30d', 'month' => __('This month'), 'quarter' => __('Quarter'), '12m' => '12m'] as $p => $label)
            <button type="button" wire:click="range('{{ $p }}')" class="btn btn-secondary px-2.5 py-1 text-xs">{{ $label }}</button>
        @endforeach
    </div>
    <select wire:model.live="location" class="input input-sm" aria-label="{{ __('Location') }}">
        @if ($locations->count() > 1) <option value="">{{ __('All my locations') }}</option> @endif
        @foreach ($locations as $l) <option value="{{ $l->id }}">{{ $l->name }}</option> @endforeach
    </select>
    @isset($departments)
        <select wire:model.live="department" class="input input-sm" aria-label="{{ __('Department') }}">
            <option value="">{{ __('All departments') }}</option>
            @foreach ($departments as $d) <option value="{{ $d->id }}">{{ $d->name }}</option> @endforeach
        </select>
        <select wire:model.live="service" class="input input-sm" aria-label="{{ __('Service') }}">
            <option value="">{{ __('All services') }}</option>
            @foreach ($services as $s) <option value="{{ $s->id }}">{{ $s->name }}</option> @endforeach
        </select>
        <select wire:model.live="employee" class="input input-sm" aria-label="{{ __('Employee') }}">
            <option value="">{{ __('All employees') }}</option>
            @foreach ($employees as $e) <option value="{{ $e->id }}">{{ $e->display_name }}</option> @endforeach
        </select>
    @endisset
</div>
