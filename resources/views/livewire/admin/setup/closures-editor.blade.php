<div class="space-y-4">
    <form wire:submit="add" class="grid gap-3 card p-4 sm:grid-cols-6">
        <div>
            <label class="form-label" for="clo-date">{{ __('Date') }}</label>
            <input id="clo-date" type="date" wire:model="date" class="input mt-1 w-full">
            @error('date') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="form-label" for="clo-dep">{{ __('Applies to') }}</label>
            <select id="clo-dep" wire:model="department_id" class="input mt-1 w-full">
                <option value="">{{ __('Whole location') }}</option>
                @foreach ($departments as $d) <option value="{{ $d->id }}">{{ $d->name }}</option> @endforeach
            </select>
        </div>
        <div class="sm:col-span-2">
            <label class="flex items-center gap-2 text-sm font-medium"><input type="checkbox" wire:model.live="specialHours"> {{ __('Open with special hours') }}</label>
            @if ($specialHours)
                <div class="mt-1 flex items-center gap-2">
                    <input type="time" wire:model="opens_at" class="input input-sm" aria-label="{{ __('Opens') }}">–
                    <input type="time" wire:model="closes_at" class="input input-sm" aria-label="{{ __('Closes') }}">
                </div>
                @error('closes_at') <p class="field-error">{{ $message }}</p> @enderror
            @else
                <p class="mt-1 text-sm text-slate-500">{{ __('Closed all day') }}</p>
            @endif
        </div>
        <div>
            <label class="form-label" for="clo-reason">{{ __('Reason') }}</label>
            <input id="clo-reason" wire:model="reason" placeholder="{{ __('e.g. Public holiday') }}" class="input mt-1 w-full">
        </div>
        <div class="flex items-end"><button type="submit" class="btn btn-primary">{{ __('Add') }}</button></div>
    </form>

    <table class="data-table rounded-xl bg-white shadow-sm ring-1 ring-slate-200/80">
        <thead>
            <tr><th class="px-3 py-2">{{ __('Date') }}</th><th class="px-3 py-2">{{ __('Applies to') }}</th><th class="px-3 py-2">{{ __('Hours') }}</th><th class="px-3 py-2">{{ __('Reason') }}</th><th></th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($closures as $c)
                <tr>
                    <td class="px-3 py-2">{{ $c->date->isoFormat('ddd, LL') }}</td>
                    <td class="px-3 py-2">{{ $c->department_id ? $departments[$c->department_id]?->name : __('Whole location') }}</td>
                    <td class="px-3 py-2">{{ $c->isClosedAllDay() ? __('Closed') : substr($c->opens_at, 0, 5).'–'.substr($c->closes_at, 0, 5) }}</td>
                    <td class="px-3 py-2">{{ $c->reason }}</td>
                    <td class="px-3 py-2 text-right"><button wire:click="remove({{ $c->id }})" class="text-red-600">{{ __('Remove') }}</button></td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-3 py-6 text-center text-slate-500">{{ __('No upcoming closures.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
