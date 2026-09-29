<div class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="page-title">{{ __('Appointments') }} <span class="text-base font-normal text-slate-500">· {{ $location->name }}</span></h1>
        <div class="flex items-center gap-3">
            <input type="date" wire:model.live="date" class="input input-sm" aria-label="{{ __('Date') }}">
            <button wire:click="create" class="btn btn-primary">{{ __('New appointment') }}</button>
        </div>
    </div>

    @if ($flash) <p class="alert-success" data-testid="flash">{{ $flash }}</p> @endif
    @if ($error) <p class="rounded bg-amber-50 p-3 text-amber-900" role="alert" data-testid="error">{{ $error }}</p> @endif

    @if ($editingId !== null)
        <form wire:submit="save" class="grid gap-4 card p-6 sm:grid-cols-3">
            @if ($editingId === 0)
                <div>
                    <label class="form-label" for="ap-name">{{ __('Customer name') }}</label>
                    <input id="ap-name" wire:model="name" class="input mt-1 w-full">
                    @error('name') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label" for="ap-phone">{{ __('Mobile number') }}</label>
                    <input id="ap-phone" type="tel" wire:model="phone" class="input mt-1 w-full">
                    @error('phone') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    <label class="mt-1 flex items-center gap-2 text-sm"><input type="checkbox" wire:model="smsConsent"> {{ __('Agrees to SMS') }}</label>
                </div>
                <div>
                    <label class="form-label" for="ap-email">{{ __('Email (optional)') }}</label>
                    <input id="ap-email" type="email" wire:model="email" class="input mt-1 w-full">
                </div>
            @endif
            <div>
                <label class="form-label" for="ap-service">{{ __('Service') }}</label>
                <select id="ap-service" wire:model="serviceId" class="input mt-1 w-full">
                    <option value="">—</option>
                    @foreach ($services as $s) <option value="{{ $s->id }}">{{ $s->name }}</option> @endforeach
                </select>
                @error('serviceId') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label" for="ap-emp">{{ __('Employee') }}</label>
                <select id="ap-emp" wire:model="employeeId" class="input mt-1 w-full">
                    <option value="">{{ __('First available') }}</option>
                    @foreach ($employees as $e) <option value="{{ $e->id }}">{{ $e->display_name }}</option> @endforeach
                </select>
            </div>
            <div>
                <label class="form-label" for="ap-time">{{ __('Date & time (:tz)', ['tz' => $tz]) }}</label>
                <input id="ap-time" type="datetime-local" wire:model="time" class="input mt-1 w-full">
                @error('time') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            @if ($needsOverride)
                <label class="flex items-center gap-2 text-sm text-amber-800 sm:col-span-3"><input type="checkbox" wire:model="override"> {{ __('Book anyway (override availability)') }}</label>
            @endif
            <div class="flex gap-3 sm:col-span-3">
                <button type="submit" class="btn btn-primary">{{ $editingId === 0 ? __('Book') : __('Move appointment') }}</button>
                <button type="button" wire:click="cancelEdit" class="btn btn-ghost">{{ __('Cancel') }}</button>
            </div>
        </form>
    @endif

    <div class="overflow-x-auto card">
        <table class="data-table">
            <thead>
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
                                @if ($isToday) <button wire:click="checkIn({{ $a->id }})" class="btn bg-emerald-600 px-2.5 py-1 text-xs text-white hover:bg-emerald-700">{{ __('Check in') }}</button> @endif
                                <button wire:click="edit({{ $a->id }})" class="link">{{ __('Reschedule') }}</button>
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
