<div class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold">{{ __('Appointments') }} <span class="text-base font-normal text-slate-500">· {{ $location->name }}</span></h1>
        <div class="flex items-center gap-3">
            <input type="date" wire:model.live="date" class="rounded border border-slate-300 px-2 py-1" aria-label="{{ __('Date') }}">
            <button wire:click="create" class="rounded bg-slate-900 px-4 py-2 text-white">{{ __('New appointment') }}</button>
        </div>
    </div>

    @if ($flash) <p class="rounded bg-green-50 p-3 text-green-800" data-testid="flash">{{ $flash }}</p> @endif
    @if ($error) <p class="rounded bg-amber-50 p-3 text-amber-900" role="alert" data-testid="error">{{ $error }}</p> @endif

    @if ($editingId !== null)
        <form wire:submit="save" class="grid gap-4 rounded-lg bg-white p-6 shadow-sm sm:grid-cols-3">
            @if ($editingId === 0)
                <div>
                    <label class="block text-sm font-medium" for="ap-name">{{ __('Customer name') }}</label>
                    <input id="ap-name" wire:model="name" class="mt-1 w-full rounded border border-slate-300 px-2 py-2">
                    @error('name') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium" for="ap-phone">{{ __('Mobile number') }}</label>
                    <input id="ap-phone" type="tel" wire:model="phone" class="mt-1 w-full rounded border border-slate-300 px-2 py-2">
                    @error('phone') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    <label class="mt-1 flex items-center gap-2 text-sm"><input type="checkbox" wire:model="smsConsent"> {{ __('Agrees to SMS') }}</label>
                </div>
                <div>
                    <label class="block text-sm font-medium" for="ap-email">{{ __('Email (optional)') }}</label>
                    <input id="ap-email" type="email" wire:model="email" class="mt-1 w-full rounded border border-slate-300 px-2 py-2">
                </div>
            @endif
            <div>
                <label class="block text-sm font-medium" for="ap-service">{{ __('Service') }}</label>
                <select id="ap-service" wire:model="serviceId" class="mt-1 w-full rounded border border-slate-300 px-2 py-2">
                    <option value="">—</option>
                    @foreach ($services as $s) <option value="{{ $s->id }}">{{ $s->name }}</option> @endforeach
                </select>
                @error('serviceId') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium" for="ap-emp">{{ __('Employee') }}</label>
                <select id="ap-emp" wire:model="employeeId" class="mt-1 w-full rounded border border-slate-300 px-2 py-2">
                    <option value="">{{ __('First available') }}</option>
                    @foreach ($employees as $e) <option value="{{ $e->id }}">{{ $e->display_name }}</option> @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium" for="ap-time">{{ __('Date & time (:tz)', ['tz' => $tz]) }}</label>
                <input id="ap-time" type="datetime-local" wire:model="time" class="mt-1 w-full rounded border border-slate-300 px-2 py-2">
                @error('time') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            @if ($needsOverride)
                <label class="flex items-center gap-2 text-sm text-amber-800 sm:col-span-3"><input type="checkbox" wire:model="override"> {{ __('Book anyway (override availability)') }}</label>
            @endif
            <div class="flex gap-3 sm:col-span-3">
                <button type="submit" class="rounded bg-slate-900 px-4 py-2 text-white">{{ $editingId === 0 ? __('Book') : __('Move appointment') }}</button>
                <button type="button" wire:click="cancelEdit" class="px-4 py-2">{{ __('Cancel') }}</button>
            </div>
        </form>
    @endif

    <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-slate-500">
                <tr>
                    <th class="px-3 py-2">{{ __('Time') }}</th>
                    <th class="px-3 py-2">{{ __('Customer') }}</th>
                    <th class="px-3 py-2">{{ __('Service') }}</th>
                    <th class="px-3 py-2">{{ __('Employee') }}</th>
                    <th class="px-3 py-2">{{ __('Status') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($appointments as $a)
                    <tr wire:key="appt-{{ $a->id }}" data-testid="appointment-{{ $a->confirmation_code }}">
                        <td class="whitespace-nowrap px-3 py-2 font-mono">{{ $a->starts_at->setTimezone($tz)->format('H:i') }}</td>
                        <td class="px-3 py-2">{{ $a->customer_name }}<div class="text-xs text-slate-500">{{ $a->customer_phone }} · {{ $a->confirmation_code }}</div></td>
                        <td class="px-3 py-2">{{ $a->service->name }}</td>
                        <td class="px-3 py-2">{{ $a->employee?->display_name ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $a->status->label() }}</td>
                        <td class="space-x-2 whitespace-nowrap px-3 py-2 text-right">
                            @if ($a->status->isUpcoming())
                                @if ($isToday) <button wire:click="checkIn({{ $a->id }})" class="rounded bg-green-600 px-2 py-1 text-white">{{ __('Check in') }}</button> @endif
                                <button wire:click="edit({{ $a->id }})" class="underline">{{ __('Reschedule') }}</button>
                                <button wire:click="cancel({{ $a->id }})" wire:confirm="{{ __('Cancel this appointment? The customer will be notified.') }}" class="text-red-600 underline">{{ __('Cancel') }}</button>
                                @if ($a->starts_at->isPast()) <button wire:click="noShow({{ $a->id }})" class="text-red-600 underline">{{ __('No-show') }}</button> @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-3 py-6 text-center text-slate-500">{{ __('No appointments on this day.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
