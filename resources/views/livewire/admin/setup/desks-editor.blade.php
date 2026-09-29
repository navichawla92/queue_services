<div class="space-y-4">
    <form wire:submit="save" class="grid gap-3 card p-4 sm:grid-cols-5">
        <div class="sm:col-span-2">
            <label class="form-label" for="desk-label">{{ __('Label shown on the lobby display') }}</label>
            <input id="desk-label" wire:model="label" placeholder="{{ __('e.g. Desk 3, Room B') }}" class="input mt-1 w-full">
            @error('label') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="form-label" for="desk-dep">{{ __('Department (optional)') }}</label>
            <select id="desk-dep" wire:model="department_id" class="input mt-1 w-full">
                <option value="">—</option>
                @foreach ($departments as $d) <option value="{{ $d->id }}">{{ $d->name }}</option> @endforeach
            </select>
            @error('department_id') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="form-label" for="desk-sort">{{ __('Order') }}</label>
            <input id="desk-sort" type="number" min="0" wire:model="sort_order" class="input mt-1 w-full">
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="btn btn-primary">{{ $editingId ? __('Save') : __('Add') }}</button>
            @if ($editingId) <button type="button" wire:click="cancel" class="px-2 py-2 text-sm">{{ __('Cancel') }}</button> @endif
        </div>
    </form>

    <table class="data-table rounded-xl bg-white shadow-sm ring-1 ring-slate-200/80">
        <thead>
            <tr><th class="px-3 py-2">{{ __('Label') }}</th><th class="px-3 py-2">{{ __('Department') }}</th><th class="px-3 py-2">{{ __('Status') }}</th><th></th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($desks as $desk)
                <tr @class(['opacity-50' => ! $desk->is_active])>
                    <td class="px-3 py-2 font-medium">{{ $desk->label }}</td>
                    <td class="px-3 py-2">{{ $desk->department?->name ?? '—' }}</td>
                    <td class="px-3 py-2">{{ $desk->is_active ? __('Active') : __('Inactive') }}</td>
                    <td class="space-x-3 px-3 py-2 text-right">
                        <button wire:click="edit({{ $desk->id }})" class="link">{{ __('Edit') }}</button>
                        <button wire:click="setActive({{ $desk->id }}, {{ $desk->is_active ? 'false' : 'true' }})" class="{{ $desk->is_active ? 'text-red-600' : 'text-green-700' }}">
                            {{ $desk->is_active ? __('Deactivate') : __('Reactivate') }}
                        </button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-3 py-6 text-center text-slate-500">{{ __('No desks or rooms yet.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
