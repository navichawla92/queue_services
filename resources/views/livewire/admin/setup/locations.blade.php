<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">{{ __('Locations') }}</h1>
        <div class="flex items-center gap-4">
            <label class="text-sm"><input type="checkbox" wire:model.live="showInactive"> {{ __('Show inactive') }}</label>
            @can('locations.manage')
                <button wire:click="create" class="rounded bg-slate-900 px-4 py-2 text-white">{{ __('New location') }}</button>
            @endcan
        </div>
    </div>

    @if ($editingId !== null)
        <form wire:submit="save" class="grid gap-4 rounded-lg bg-white p-6 shadow-sm sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium" for="loc-name">{{ __('Name') }}</label>
                <input id="loc-name" wire:model="name" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium" for="loc-phone">{{ __('Contact phone') }}</label>
                <input id="loc-phone" wire:model="phone" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
                @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium" for="loc-address">{{ __('Address') }}</label>
                <textarea id="loc-address" wire:model="address" rows="2" class="mt-1 w-full rounded border border-slate-300 px-3 py-2"></textarea>
                @error('address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium" for="loc-tz">{{ __('Time zone') }}</label>
                <select id="loc-tz" wire:model="timezone" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
                    <option value="">{{ __('Company default (:tz)', ['tz' => $tenantTimezone]) }}</option>
                    @foreach ($timezones as $tz)
                        <option value="{{ $tz }}">{{ $tz }}</option>
                    @endforeach
                </select>
                @error('timezone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium" for="loc-cutoff">{{ __('Last walk-in (minutes before closing)') }}</label>
                <input id="loc-cutoff" type="number" min="0" max="240" wire:model="walkin_cutoff_minutes" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
                @error('walkin_cutoff_minutes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="flex gap-3 sm:col-span-2">
                <button type="submit" class="rounded bg-slate-900 px-4 py-2 text-white">{{ __('Save') }}</button>
                <button type="button" wire:click="cancel" class="rounded px-4 py-2">{{ __('Cancel') }}</button>
            </div>
        </form>
    @endif

    <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-slate-500">
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
                            <a href="{{ route('admin.locations.show', $location) }}" class="font-medium underline">{{ $location->name }}</a>
                            <div class="text-slate-500">{{ $location->address }}</div>
                        </td>
                        <td class="px-3 py-2">{{ $location->timezone ?? __('Default') }}</td>
                        <td class="px-3 py-2">{{ $location->is_active ? __('Active') : __('Inactive') }}</td>
                        <td class="space-x-3 px-3 py-2 text-right">
                            <button wire:click="edit({{ $location->id }})" class="underline">{{ __('Edit') }}</button>
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
