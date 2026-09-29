<div class="space-y-6">
    <div class="flex items-center justify-between gap-4">
        <h1 class="page-title">{{ __('Staff') }}</h1>
        <div class="flex items-center gap-3">
            <input wire:model.live.debounce.300ms="search" placeholder="{{ __('Search name or email') }}" class="input text-sm">
            @if ($canManageUsers)
                <button wire:click="create" class="btn btn-primary">{{ __('Add staff member') }}</button>
            @endif
        </div>
    </div>

    @if ($flash) <p class="rounded bg-green-50 p-3 text-sm text-green-800">{{ $flash }}</p> @endif

    @if ($editingId !== null)
        <form wire:submit="save" class="grid gap-4 card p-6 lg:grid-cols-2">
            @if ($canManageUsers)
                <div>
                    <label class="form-label" for="emp-name">{{ __('Full name') }}</label>
                    <input id="emp-name" wire:model="name" class="input mt-1 w-full">
                    @error('name') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label" for="emp-email">{{ __('Email (sign-in)') }}</label>
                    <input id="emp-email" type="email" wire:model="email" class="input mt-1 w-full">
                    @error('email') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label" for="emp-role">{{ __('Role') }}</label>
                    <select id="emp-role" wire:model="role" class="input mt-1 w-full">
                        @foreach ($roles as $r) <option value="{{ $r }}">{{ \Illuminate\Support\Str::headline($r) }}</option> @endforeach
                    </select>
                    @error('role') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <span class="form-label">{{ __('Locations') }}</span>
                    <label class="mt-1 flex items-center gap-2 text-sm"><input type="checkbox" wire:model.live="all_locations"> {{ __('All locations') }}</label>
                    @unless ($all_locations)
                        <div class="mt-1 flex flex-wrap gap-3 text-sm">
                            @foreach ($locations as $loc)
                                <label class="flex items-center gap-1"><input type="checkbox" value="{{ $loc->id }}" wire:model.live="location_ids"> {{ $loc->name }}</label>
                            @endforeach
                        </div>
                    @endunless
                    @error('location_ids.*') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            @endif

            <div>
                <label class="form-label" for="emp-display">{{ __('Display name (shown on the lobby TV)') }}</label>
                <input id="emp-display" wire:model="display_name" maxlength="50" class="input mt-1 w-full">
                @error('display_name') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label" for="emp-desk">{{ __('Default desk / room') }}</label>
                <select id="emp-desk" wire:model="default_desk_id" class="input mt-1 w-full">
                    <option value="">—</option>
                    @foreach ($desks as $desk) <option value="{{ $desk->id }}">{{ $desk->location->name }} · {{ $desk->label }}</option> @endforeach
                </select>
                @error('default_desk_id') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <span class="form-label">{{ __('Departments') }}</span>
                <div class="mt-1 grid gap-1 text-sm">
                    @forelse ($departments as $d)
                        <label class="flex items-center gap-2"><input type="checkbox" value="{{ $d->id }}" wire:model="department_ids"> {{ $d->location->name }} · {{ $d->name }}</label>
                    @empty
                        <span class="text-slate-500">{{ __('Choose locations first.') }}</span>
                    @endforelse
                </div>
                @error('department_ids.*') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <span class="form-label">{{ __('Skills (services this person can serve)') }}</span>
                <div class="mt-1 grid gap-1 text-sm">
                    @foreach ($services as $s)
                        <label class="flex items-center gap-2"><input type="checkbox" value="{{ $s->id }}" wire:model="service_ids"> {{ $s->name }}</label>
                    @endforeach
                </div>
                @error('service_ids.*') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div class="flex gap-3 lg:col-span-2">
                <button type="submit" class="btn btn-primary">{{ $editingId ? __('Save') : __('Create and send invitation') }}</button>
                <button type="button" wire:click="cancel" class="btn btn-ghost">{{ __('Cancel') }}</button>
            </div>
        </form>
    @endif

    <table class="data-table rounded-xl bg-white shadow-sm ring-1 ring-slate-200/80">
        <thead>
            <tr>
                <th class="px-3 py-2">{{ __('Name') }}</th>
                <th class="px-3 py-2">{{ __('Locations') }}</th>
                <th class="px-3 py-2">{{ __('Departments') }}</th>
                <th class="px-3 py-2">{{ __('Skills') }}</th>
                <th class="px-3 py-2">{{ __('Status') }}</th>
                <th></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($employees as $e)
                <tr @class(['opacity-50' => ! $e->user->is_active])>
                    <td class="px-3 py-2">
                        <div class="font-medium">{{ $e->display_name }}</div>
                        <div class="text-slate-500">{{ $e->user->name }} · {{ $e->user->email }}</div>
                    </td>
                    <td class="px-3 py-2">{{ $e->user->all_locations ? __('All') : $e->user->locations->pluck('name')->join(', ') }}</td>
                    <td class="px-3 py-2">{{ $e->departments->pluck('name')->join(', ') }}</td>
                    <td class="px-3 py-2">{{ $e->services->pluck('name')->join(', ') }}</td>
                    <td class="px-3 py-2">{{ $e->user->is_active ? $e->status->label() : __('Deactivated') }}</td>
                    <td class="space-x-3 whitespace-nowrap px-3 py-2 text-right">
                        <button wire:click="edit({{ $e->id }})" class="link">{{ __('Edit') }}</button>
                        <a href="{{ route('admin.employees.availability', $e) }}" class="link">{{ __('Availability') }}</a>
                        @if ($canManageUsers && $e->user_id !== auth()->id())
                            @if ($e->user->is_active)
                                <button wire:click="setActive({{ $e->id }}, false)" wire:confirm="{{ __('Deactivate this account? They will be signed out immediately.') }}" class="text-red-600">{{ __('Deactivate') }}</button>
                            @else
                                <button wire:click="setActive({{ $e->id }}, true)" class="text-green-700">{{ __('Reactivate') }}</button>
                            @endif
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-3 py-6 text-center text-slate-500">{{ __('No staff yet.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
