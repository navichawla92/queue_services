<div class="space-y-4">
    <form wire:submit="save" class="grid gap-3 rounded-lg bg-white p-4 shadow-sm sm:grid-cols-5">
        <div class="sm:col-span-2">
            <label class="block text-sm font-medium" for="desk-label">{{ __('Label shown on the lobby display') }}</label>
            <input id="desk-label" wire:model="label" placeholder="{{ __('e.g. Desk 3, Room B') }}" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
            @error('label') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium" for="desk-dep">{{ __('Department (optional)') }}</label>
            <select id="desk-dep" wire:model="department_id" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
                <option value="">—</option>
                @foreach ($departments as $d) <option value="{{ $d->id }}">{{ $d->name }}</option> @endforeach
            </select>
            @error('department_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium" for="desk-sort">{{ __('Order') }}</label>
            <input id="desk-sort" type="number" min="0" wire:model="sort_order" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="rounded bg-slate-900 px-4 py-2 text-white">{{ $editingId ? __('Save') : __('Add') }}</button>
            @if ($editingId) <button type="button" wire:click="cancel" class="px-2 py-2 text-sm">{{ __('Cancel') }}</button> @endif
        </div>
    </form>

    <table class="min-w-full rounded-lg bg-white text-sm shadow-sm">
        <thead class="bg-slate-50 text-left text-slate-500">
            <tr><th class="px-3 py-2">{{ __('Label') }}</th><th class="px-3 py-2">{{ __('Department') }}</th><th class="px-3 py-2">{{ __('Status') }}</th><th></th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($desks as $desk)
                <tr @class(['opacity-50' => ! $desk->is_active])>
                    <td class="px-3 py-2 font-medium">{{ $desk->label }}</td>
                    <td class="px-3 py-2">{{ $desk->department?->name ?? '—' }}</td>
                    <td class="px-3 py-2">{{ $desk->is_active ? __('Active') : __('Inactive') }}</td>
                    <td class="space-x-3 px-3 py-2 text-right">
                        <button wire:click="edit({{ $desk->id }})" class="underline">{{ __('Edit') }}</button>
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
