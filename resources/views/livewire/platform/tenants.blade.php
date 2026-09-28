<div class="space-y-6" data-testid="platform-console">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">{{ __('Tenants') }}</h1>
        <button wire:click="$set('creating', true)" class="rounded bg-slate-900 px-4 py-2 text-white">{{ __('New tenant') }}</button>
    </div>
    @if ($flash) <p class="rounded bg-green-50 p-3 text-green-800">{{ $flash }}</p> @endif

    @if ($creating)
        <form wire:submit="create" class="grid gap-4 rounded-lg bg-white p-6 shadow-sm sm:grid-cols-3">
            <div>
                <label class="block text-sm font-medium" for="t-name">{{ __('Company name') }}</label>
                <input id="t-name" wire:model="name" class="mt-1 w-full rounded border border-slate-300 px-2 py-2">
                @error('name') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium" for="t-plan">{{ __('Plan') }}</label>
                <select id="t-plan" wire:model="planId" class="mt-1 w-full rounded border border-slate-300 px-2 py-2">
                    <option value="">—</option>
                    @foreach ($plans as $p) <option value="{{ $p->id }}">{{ $p->name }}</option> @endforeach
                </select>
                @error('planId') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium" for="t-tz">{{ __('Time zone') }}</label>
                <select id="t-tz" wire:model="timezone" class="mt-1 w-full rounded border border-slate-300 px-2 py-2">
                    @foreach ($timezones as $tz) <option value="{{ $tz }}">{{ $tz }}</option> @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium" for="t-admin">{{ __('First company admin') }}</label>
                <input id="t-admin" wire:model="adminName" class="mt-1 w-full rounded border border-slate-300 px-2 py-2">
                @error('adminName') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium" for="t-email">{{ __('Admin email (invitation)') }}</label>
                <input id="t-email" type="email" wire:model="adminEmail" class="mt-1 w-full rounded border border-slate-300 px-2 py-2">
                @error('adminEmail') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="flex items-end gap-3">
                <button type="submit" class="rounded bg-slate-900 px-4 py-2 text-white">{{ __('Create & invite') }}</button>
                <button type="button" wire:click="$set('creating', false)" class="px-4 py-2">{{ __('Cancel') }}</button>
            </div>
        </form>
    @endif

    <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-slate-500">
                <tr>
                    <th class="px-3 py-2">{{ __('Tenant') }}</th><th class="px-3 py-2">{{ __('Plan') }}</th><th class="px-3 py-2">{{ __('Status') }}</th>
                    <th class="px-3 py-2">{{ __('Locations') }}</th><th class="px-3 py-2">{{ __('Users') }}</th><th class="px-3 py-2">{{ __('SMS (month)') }}</th><th class="px-3 py-2">{{ __('Tickets (month)') }}</th>
                    <th class="px-3 py-2">{{ __('Support') }}</th><th></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($tenants as $t)
                    <tr wire:key="tenant-{{ $t->id }}" data-testid="tenant-{{ $t->id }}">
                        <td class="px-3 py-2 font-medium">{{ $t->name }}</td>
                        <td class="px-3 py-2">
                            <select wire:change="changePlan({{ $t->id }}, $event.target.value)" class="rounded border border-slate-300 px-1 py-0.5" aria-label="{{ __('Plan') }}">
                                @foreach ($plans as $p) <option value="{{ $p->id }}" @selected($p->id === $t->plan_id)>{{ $p->name }}</option> @endforeach
                            </select>
                        </td>
                        <td class="px-3 py-2">{{ $t->isActive() ? __('Active') : __('Suspended') }}</td>
                        <td class="px-3 py-2">{{ $usage[$t->id]['locations'] }}</td>
                        <td class="px-3 py-2">{{ $usage[$t->id]['users'] }}</td>
                        <td class="px-3 py-2">{{ $usage[$t->id]['sms'] }}</td>
                        <td class="px-3 py-2">{{ $usage[$t->id]['tickets'] }}</td>
                        <td class="px-3 py-2">
                            <form method="POST" action="{{ route('platform.support.start', $t) }}" class="flex gap-1">
                                @csrf
                                <input name="reason" required minlength="5" placeholder="{{ __('Reason') }}" class="w-32 rounded border border-slate-300 px-1 py-0.5">
                                <button class="rounded bg-amber-500 px-2 py-0.5 text-white">{{ __('Open') }}</button>
                            </form>
                        </td>
                        <td class="px-3 py-2 text-right">
                            @if ($t->isActive())
                                <button wire:click="setStatus({{ $t->id }}, false)" wire:confirm="{{ __('Suspend :name? Its staff are signed out and its public pages stop.', ['name' => $t->name]) }}" class="text-red-600">{{ __('Suspend') }}</button>
                            @else
                                <button wire:click="setStatus({{ $t->id }}, true)" class="text-green-700">{{ __('Reactivate') }}</button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
