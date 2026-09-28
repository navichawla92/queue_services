<div class="space-y-6">
    <h1 class="text-2xl font-semibold">{{ __('Kiosks & lobby displays') }}</h1>

    <form wire:submit="pair" class="grid gap-4 rounded-lg bg-white p-6 shadow-sm sm:grid-cols-4">
        <p class="sm:col-span-4 text-sm text-slate-600">
            {{ __('Open :display (TV) or :kiosk (tablet) on the device, then enter the code it shows.', ['display' => url('/display'), 'kiosk' => url('/kiosk')]) }}
        </p>
        <div>
            <label class="block text-sm font-medium" for="code">{{ __('Pairing code') }}</label>
            <input id="code" wire:model="code" maxlength="6" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 font-mono uppercase">
            @error('code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium" for="location_id">{{ __('Location') }}</label>
            <select id="location_id" wire:model="location_id" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
                @foreach ($locations as $loc)
                    <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                @endforeach
            </select>
            @error('location_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium" for="name">{{ __('Name') }}</label>
            <input id="name" wire:model="name" placeholder="{{ __('e.g. Lobby TV 1') }}" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div class="flex items-end">
            <button type="submit" class="rounded bg-slate-900 px-4 py-2 text-white">{{ __('Pair device') }}</button>
        </div>
        @if ($pairedMessage)
            <p class="sm:col-span-4 text-sm text-green-700">{{ $pairedMessage }}</p>
        @endif
    </form>

    <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-slate-500">
                <tr>
                    <th class="px-3 py-2">{{ __('Name') }}</th>
                    <th class="px-3 py-2">{{ __('Type') }}</th>
                    <th class="px-3 py-2">{{ __('Location') }}</th>
                    <th class="px-3 py-2">{{ __('Last seen') }}</th>
                    <th class="px-3 py-2">{{ __('Status') }}</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($devices as $device)
                    <tr>
                        <td class="px-3 py-2">{{ $device->name }}</td>
                        <td class="px-3 py-2">{{ $device->type }}</td>
                        <td class="px-3 py-2">{{ $device->location->name }}</td>
                        <td class="px-3 py-2">{{ $device->last_seen_at?->diffForHumans() ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $device->isRevoked() ? __('Revoked') : __('Active') }}</td>
                        <td class="px-3 py-2 text-right">
                            @unless ($device->isRevoked())
                                <button wire:click="revoke({{ $device->id }})" wire:confirm="{{ __('Revoke this device? It will return to the pairing screen.') }}" class="text-red-600">{{ __('Revoke') }}</button>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-3 py-6 text-center text-slate-500">{{ __('No devices paired yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
