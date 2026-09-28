<div class="space-y-4">
    <div class="flex items-center gap-3 text-sm">
        <label for="hours-scope" class="font-medium">{{ __('Hours for') }}</label>
        <select id="hours-scope" wire:model.live="scope" class="rounded border border-slate-300 px-2 py-1">
            <option value="">{{ __('Whole location') }}</option>
            @foreach ($departments as $d) <option value="{{ $d->id }}">{{ __('Department: :name', ['name' => $d->name]) }}</option> @endforeach
        </select>
    </div>
    @if ($scope !== '')
        <p class="text-sm text-slate-500">{{ __('A department without its own rows uses the location hours. Department hours are always limited to the location hours.') }}</p>
    @endif

    <form wire:submit="save" class="space-y-3 rounded-lg bg-white p-4 shadow-sm">
        @forelse ($rows as $i => $row)
            <div class="flex flex-wrap items-start gap-3" wire:key="row-{{ $i }}">
                <select wire:model="rows.{{ $i }}.weekday" class="rounded border border-slate-300 px-2 py-1" aria-label="{{ __('Day') }}">
                    @foreach ($weekdays as $num => $name) <option value="{{ $num }}">{{ $name }}</option> @endforeach
                </select>
                <input type="time" wire:model="rows.{{ $i }}.opens_at" class="rounded border border-slate-300 px-2 py-1" aria-label="{{ __('Opens') }}">
                <span class="py-1">–</span>
                <div>
                    <input type="time" wire:model="rows.{{ $i }}.closes_at" class="rounded border border-slate-300 px-2 py-1" aria-label="{{ __('Closes') }}">
                    @error('rows.'.$i.'.closes_at') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <button type="button" wire:click="removeRow({{ $i }})" class="py-1 text-sm text-red-600">{{ __('Remove') }}</button>
            </div>
        @empty
            <p class="text-sm text-slate-500">{{ $scope === '' ? __('No hours set: the location is closed.') : __('No department hours: uses location hours.') }}</p>
        @endforelse

        <div class="flex items-center gap-3 pt-2">
            <button type="button" wire:click="addRow" class="rounded border border-slate-300 px-3 py-1 text-sm">{{ __('Add time range') }}</button>
            <button type="submit" class="rounded bg-slate-900 px-4 py-2 text-white">{{ __('Save hours') }}</button>
            @if ($saved) <span class="text-sm text-green-700">{{ __('Saved.') }}</span> @endif
        </div>
    </form>
</div>
