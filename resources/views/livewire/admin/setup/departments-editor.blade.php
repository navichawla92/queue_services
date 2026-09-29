<div class="space-y-4">
    <form wire:submit="save" class="grid gap-3 card p-4 sm:grid-cols-6">
        <div class="sm:col-span-2">
            <label class="form-label" for="dep-name">{{ __('Department name') }}</label>
            <input id="dep-name" wire:model="name" class="input mt-1 w-full">
            @error('name') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="form-label" for="dep-prefix">{{ __('Ticket prefix') }}</label>
            <input id="dep-prefix" wire:model="prefix" maxlength="3" class="input mt-1 w-full font-mono uppercase">
            @error('prefix') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="form-label" for="dep-color">{{ __('Color') }}</label>
            <input id="dep-color" type="color" wire:model="color" class="mt-1 h-10 w-full">
            @error('color') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="form-label" for="dep-sort">{{ __('Order') }}</label>
            <input id="dep-sort" type="number" min="0" wire:model="sort_order" class="input mt-1 w-full">
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="btn btn-primary">{{ $editingId ? __('Save') : __('Add') }}</button>
            @if ($editingId) <button type="button" wire:click="cancel" class="px-2 py-2 text-sm">{{ __('Cancel') }}</button> @endif
        </div>
    </form>

    <table class="data-table rounded-xl bg-white shadow-sm ring-1 ring-slate-200/80">
        <thead>
            <tr><th class="px-3 py-2">{{ __('Prefix') }}</th><th class="px-3 py-2">{{ __('Name') }}</th><th class="px-3 py-2">{{ __('Status') }}</th><th></th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($departments as $d)
                <tr @class(['opacity-50' => ! $d->is_active])>
                    <td class="px-3 py-2"><span class="rounded px-2 py-0.5 font-mono text-white" style="background: {{ $d->color }}">{{ $d->prefix }}</span></td>
                    <td class="px-3 py-2">{{ $d->name }}</td>
                    <td class="px-3 py-2">{{ $d->is_active ? __('Active') : __('Inactive') }}</td>
                    <td class="space-x-3 px-3 py-2 text-right">
                        <button wire:click="edit({{ $d->id }})" class="link">{{ __('Edit') }}</button>
                        <button wire:click="setActive({{ $d->id }}, {{ $d->is_active ? 'false' : 'true' }})" class="{{ $d->is_active ? 'text-red-600' : 'text-green-700' }}">
                            {{ $d->is_active ? __('Deactivate') : __('Reactivate') }}
                        </button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-3 py-6 text-center text-slate-500">{{ __('No departments yet.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
