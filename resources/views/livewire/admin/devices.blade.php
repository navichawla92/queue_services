<div class="space-y-6">
    <h1 class="page-title">{{ __('Kiosks & lobby displays') }}</h1>

    @can('tenant.manage')
        <label class="flex items-center gap-2 card p-4 text-sm">
            <input type="checkbox" wire:model.live="showNames">
            {{ __('Show customers as "First name + last initial" on lobby displays (company-wide). Off: ticket numbers only. Full names are never shown.') }}
        </label>
    @endcan

    @if ($configuring)
        <form wire:submit="saveConfig" class="grid gap-4 rounded-lg border-2 border-slate-900 bg-white p-6 shadow-sm sm:grid-cols-3" data-testid="display-config">
            <h2 class="text-lg font-semibold sm:col-span-3">{{ __('Display settings: :name', ['name' => $configuring->name]) }}</h2>
            <div>
                <label class="form-label" for="cfg-layout">{{ __('Layout') }}</label>
                <select id="cfg-layout" wire:model="config.layout" class="input mt-1 w-full">
                    <option value="queue">{{ __('Queue only (full screen)') }}</option>
                    <option value="split">{{ __('Split: queue + signage') }}</option>
                </select>
            </div>
            <div>
                <label class="form-label" for="cfg-orient">{{ __('Orientation') }}</label>
                <select id="cfg-orient" wire:model="config.orientation" class="input mt-1 w-full">
                    <option value="landscape">{{ __('Landscape') }}</option>
                    <option value="portrait">{{ __('Portrait') }}</option>
                </select>
            </div>
            <div>
                <label class="form-label" for="cfg-zone">{{ __('Queue zone (split)') }}</label>
                <select id="cfg-zone" wire:model="config.queue_zone" class="input mt-1 w-full">
                    <option value="left">{{ __('Left / top') }}</option>
                    <option value="right">{{ __('Right') }}</option>
                </select>
            </div>
            <div class="sm:col-span-3">
                <span class="form-label">{{ __('Departments shown (none = all)') }}</span>
                <div class="mt-1 flex flex-wrap gap-4 text-sm">
                    @foreach ($configDepartments as $d)
                        <label class="flex items-center gap-1"><input type="checkbox" value="{{ $d->id }}" wire:model="config.department_ids"> {{ $d->name }}</label>
                    @endforeach
                </div>
            </div>
            <div>
                <label class="form-label" for="cfg-rows">{{ __('Waiting rows') }}</label>
                <input id="cfg-rows" type="number" min="0" max="30" wire:model="config.waiting_rows" class="input mt-1 w-full">
                @error('config.waiting_rows') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label" for="cfg-hl">{{ __('Call highlight (seconds)') }}</label>
                <input id="cfg-hl" type="number" min="3" max="60" wire:model="config.highlight_seconds" class="input mt-1 w-full">
                @error('config.highlight_seconds') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="space-y-1 text-sm">
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="config.chime"> {{ __('Chime on call') }}</label>
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="config.show_employee_name"> {{ __('Show employee name') }}</label>
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="config.show_avg_wait"> {{ __('Average wait per department') }}</label>
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="config.header"> {{ __('Header with logo & clock') }}</label>
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="config.ticker"> {{ __('Scrolling ticker') }}</label>
            </div>
            <div class="flex gap-3 sm:col-span-3">
                <button type="submit" class="btn btn-primary">{{ __('Save — applies to the TV within seconds') }}</button>
                <button type="button" wire:click="$set('configuringId', null)" class="btn btn-ghost">{{ __('Cancel') }}</button>
            </div>
        </form>
    @endif

    <form wire:submit="pair" class="grid gap-4 card p-6 sm:grid-cols-4">
        <p class="sm:col-span-4 text-sm text-slate-600">
            {{ __('Open :display (TV) or :kiosk (tablet) on the device, then enter the code it shows.', ['display' => url('/display'), 'kiosk' => url('/kiosk')]) }}
        </p>
        <div>
            <label class="form-label" for="code">{{ __('Pairing code') }}</label>
            <input id="code" wire:model="code" maxlength="6" class="input mt-1 w-full font-mono uppercase">
            @error('code') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="form-label" for="location_id">{{ __('Location') }}</label>
            <select id="location_id" wire:model="location_id" class="input mt-1 w-full">
                @foreach ($locations as $loc)
                    <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                @endforeach
            </select>
            @error('location_id') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="form-label" for="name">{{ __('Name') }}</label>
            <input id="name" wire:model="name" placeholder="{{ __('e.g. Lobby TV 1') }}" class="input mt-1 w-full">
            @error('name') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div class="flex items-end">
            <button type="submit" class="btn btn-primary">{{ __('Pair device') }}</button>
        </div>
        @if ($pairedMessage)
            <p class="sm:col-span-4 text-sm text-green-700">{{ $pairedMessage }}</p>
        @endif
    </form>

    <div class="overflow-x-auto card">
        <table class="data-table">
            <thead>
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
                            @if (! $device->isRevoked() && $device->type === 'display')
                                <button wire:click="configure({{ $device->id }})" class="mr-3 underline">{{ __('Settings') }}</button>
                            @endif
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
