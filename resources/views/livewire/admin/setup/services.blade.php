<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="page-title">{{ __('Services') }}</h1>
        @if ($canEdit)
            <button wire:click="create" class="btn btn-primary">{{ __('New service') }}</button>
        @endif
    </div>
    @unless ($canEdit)
        <p class="text-sm text-slate-500">{{ __('The service catalog is managed by company administrators. Choose which services your location offers under Locations → Services offered.') }}</p>
    @endunless

    @if ($editingId !== null)
        <form wire:submit="save" class="grid gap-4 card p-6 sm:grid-cols-2">
            <div>
                <label class="form-label" for="svc-name">{{ __('Name') }}</label>
                <input id="svc-name" wire:model="name" class="input mt-1 w-full">
                @error('name') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label" for="svc-min">{{ __('Expected duration (min)') }}</label>
                    <input id="svc-min" type="number" min="1" max="480" wire:model="expected_minutes" class="input mt-1 w-full">
                    @error('expected_minutes') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label" for="svc-sort">{{ __('Order') }}</label>
                    <input id="svc-sort" type="number" min="0" wire:model="sort_order" class="input mt-1 w-full">
                </div>
            </div>
            <div class="sm:col-span-2">
                <label class="form-label" for="svc-desc">{{ __('Description (shown to customers)') }}</label>
                <textarea id="svc-desc" wire:model="description" rows="2" class="input mt-1 w-full"></textarea>
                @error('description') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div class="space-y-1 text-sm sm:col-span-2">
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="allow_walk_in"> {{ __('Available for walk-ins') }}</label>
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="allow_appointment"> {{ __('Bookable as an appointment') }}</label>
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="customer_selectable"> {{ __('Customers can choose it at check-in and booking (otherwise staff only)') }}</label>
                @error('allow_walk_in') <p class="text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="flex gap-3 sm:col-span-2">
                <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
                <button type="button" wire:click="cancel" class="btn btn-ghost">{{ __('Cancel') }}</button>
            </div>
        </form>
    @endif

    <table class="data-table rounded-xl bg-white shadow-sm ring-1 ring-slate-200/80">
        <thead>
            <tr>
                <th class="px-3 py-2">{{ __('Service') }}</th>
                <th class="px-3 py-2">{{ __('Duration') }}</th>
                <th class="px-3 py-2">{{ __('Channels') }}</th>
                <th class="px-3 py-2">{{ __('Locations') }}</th>
                <th></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($services as $s)
                <tr @class(['opacity-50' => ! $s->is_active])>
                    <td class="px-3 py-2">
                        <span class="font-medium">{{ $s->name }}</span>
                        @unless ($s->customer_selectable) <span class="ml-1 text-xs text-slate-500">({{ __('staff only') }})</span> @endunless
                    </td>
                    <td class="px-3 py-2">{{ $s->expected_minutes }} min</td>
                    <td class="px-3 py-2">{{ collect([$s->allow_walk_in ? __('Walk-in') : null, $s->allow_appointment ? __('Appointment') : null])->filter()->join(' · ') }}</td>
                    <td class="px-3 py-2">{{ $s->locations_count }}</td>
                    <td class="space-x-3 px-3 py-2 text-right">
                        @if ($canEdit)
                            <button wire:click="edit({{ $s->id }})" class="link">{{ __('Edit') }}</button>
                            <button wire:click="setActive({{ $s->id }}, {{ $s->is_active ? 'false' : 'true' }})" class="{{ $s->is_active ? 'text-red-600' : 'text-green-700' }}">
                                {{ $s->is_active ? __('Deactivate') : __('Reactivate') }}
                            </button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-3 py-6 text-center text-slate-500">{{ __('No services yet.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
