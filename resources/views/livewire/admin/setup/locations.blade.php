<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="page-title">{{ __('Locations') }}</h1>
        <div class="flex items-center gap-4">
            <label class="text-sm"><input type="checkbox" wire:model.live="showInactive"> {{ __('Show inactive') }}</label>
            @can('locations.manage')
                <button wire:click="create" class="btn btn-primary">{{ __('New location') }}</button>
            @endcan
        </div>
    </div>

    @if ($editingId !== null)
        <form wire:submit="save" class="grid gap-4 card p-6 sm:grid-cols-2">
            <div>
                <label class="form-label" for="loc-name">{{ __('Name') }}</label>
                <input id="loc-name" wire:model="name" class="input mt-1 w-full">
                @error('name') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label" for="loc-phone">{{ __('Contact phone') }}</label>
                <input id="loc-phone" wire:model="phone" class="input mt-1 w-full">
                @error('phone') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="form-label" for="loc-address">{{ __('Address') }}</label>
                <textarea id="loc-address" wire:model="address" rows="2" class="input mt-1 w-full"></textarea>
                @error('address') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label" for="loc-tz">{{ __('Time zone') }}</label>
                <select id="loc-tz" wire:model="timezone" class="input mt-1 w-full">
                    <option value="">{{ __('Company default (:tz)', ['tz' => $tenantTimezone]) }}</option>
                    @foreach ($timezones as $tz)
                        <option value="{{ $tz }}">{{ $tz }}</option>
                    @endforeach
                </select>
                @error('timezone') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label" for="loc-cutoff">{{ __('Last walk-in (minutes before closing)') }}</label>
                <input id="loc-cutoff" type="number" min="0" max="240" wire:model="walkin_cutoff_minutes" class="input mt-1 w-full">
                @error('walkin_cutoff_minutes') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div class="flex gap-3 sm:col-span-2">
                <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
                <button type="button" wire:click="cancel" class="btn btn-ghost">{{ __('Cancel') }}</button>
            </div>
        </form>
    @endif

    <div class="overflow-x-auto card">
        <table class="data-table">
            <thead>
                <tr>
                    <th class="px-3 py-2">{{ __('Name') }}</th>
                    <th class="px-3 py-2">{{ __('Time zone') }}</th>
                    <th class="px-3 py-2">{{ __('Status') }}</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($locations as $location)
                    <tr>
                        <td class="px-3 py-2">
                            <a href="{{ route('admin.locations.show', $location) }}" class="link">{{ $location->name }}</a>
                            <div class="text-slate-500">{{ $location->address }}</div>
                        </td>
                        <td class="px-3 py-2">{{ $location->timezone ?? __('Default') }}</td>
                        <td class="px-3 py-2">{{ $location->is_active ? __('Active') : __('Inactive') }}</td>
                        <td class="space-x-3 px-3 py-2 text-right">
                            <button wire:click="edit({{ $location->id }})" class="link">{{ __('Edit') }}</button>
                            @can('locations.manage')
                                @if ($location->is_active)
                                    <button wire:click="setActive({{ $location->id }}, false)" wire:confirm="{{ __('Deactivate this location? It will stop accepting check-ins and bookings. History is kept.') }}" class="text-red-600">{{ __('Deactivate') }}</button>
                                @else
                                    <button wire:click="setActive({{ $location->id }}, true)" class="text-green-700">{{ __('Reactivate') }}</button>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-3 py-6 text-center text-slate-500">{{ __('No locations yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
