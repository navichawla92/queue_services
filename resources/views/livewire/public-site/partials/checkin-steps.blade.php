{{-- Shared check-in steps. $big = kiosk sizing (large touch targets). --}}
@php
    $btn = $big ? 'min-h-20 px-8 py-5 text-2xl' : 'min-h-12 px-5 py-3 text-lg';
    $input = $big ? 'px-5 py-4 text-2xl' : 'px-4 py-3 text-lg';
    $label = $big ? 'text-xl' : 'text-base';
@endphp

@if ($error)
    <p class="mb-4 rounded bg-amber-100 p-4 text-amber-900" role="alert" data-testid="checkin-error">{{ $error }}</p>
@endif

@switch($step)
    @case('start')
        <div class="space-y-6 text-center">
            <h1 class="{{ $big ? 'text-5xl' : 'text-3xl' }} font-bold">{{ __('Welcome') }}</h1>
            <p class="{{ $label }} text-slate-600">{{ $location->name }}</p>
            <button wire:click="begin" class="{{ $btn }} w-full rounded-xl bg-[var(--brand)] font-semibold text-white">{{ __('Check in') }}</button>
            @feature('appointments')
                <button wire:click="haveAppointment" class="{{ $btn }} w-full rounded-xl border-2 border-[var(--brand)] font-semibold" data-testid="have-appointment">{{ __('I have an appointment') }}</button>
            @endfeature
        </div>
        @break

    @case('appointment')
        <h1 class="{{ $big ? 'text-4xl' : 'text-2xl' }} mb-6 font-bold">{{ __('Welcome back!') }}</h1>
        <form wire:submit="submitAppointment" class="space-y-5">
            <div>
                <label for="ci-lookup" class="{{ $label }} block font-medium">{{ __('Mobile number or confirmation code') }}</label>
                <input id="ci-lookup" wire:model="lookup" autocomplete="off" class="{{ $input }} mt-1 w-full rounded-xl border border-slate-300">
                @error('lookup') <p class="mt-1 text-red-600">{{ $message }}</p> @enderror
            </div>
            @if ($appointmentNotFound)
                <div class="rounded-xl bg-amber-50 p-4" data-testid="appointment-not-found">
                    <p class="{{ $label }}">{{ __("We couldn't find an appointment for today.") }}</p>
                    <button type="button" wire:click="begin" class="{{ $btn }} mt-3 w-full rounded-xl bg-[var(--brand)] font-semibold text-white">{{ __('Check in as a walk-in') }}</button>
                </div>
            @endif
            <div class="flex gap-3">
                <button type="button" wire:click="back" class="{{ $btn }} rounded-xl border border-slate-300">{{ __('Back') }}</button>
                <button type="submit" class="{{ $btn }} flex-1 rounded-xl bg-[var(--brand)] font-semibold text-white">{{ __('Find my appointment') }}</button>
            </div>
        </form>
        @break

    @case('closed')
        <div class="space-y-4 text-center" data-testid="closed">
            <h1 class="{{ $big ? 'text-4xl' : 'text-2xl' }} font-bold">{{ $location->is_active ? __('We are not accepting walk-ins right now.') : __('This location is not currently accepting customers.') }}</h1>
            @if ($nextOpening)
                <p class="{{ $label }}">{{ __('We open again :when.', ['when' => $nextOpening->locale(app()->getLocale())->isoFormat('dddd, LT')]) }}</p>
            @endif
            @if ($location->is_active && app(\App\Domain\Billing\Features::class)->enabled('appointments'))
                <button wire:click="haveAppointment" class="{{ $btn }} w-full rounded-xl border-2 border-[var(--brand)] font-semibold">{{ __('I have an appointment') }}</button>
            @endif
            <button wire:click="startOver" class="{{ $btn }} rounded-xl border border-slate-300">{{ __('Back') }}</button>
        </div>
        @break

    @case('service')
        <h1 class="{{ $big ? 'text-4xl' : 'text-2xl' }} mb-6 font-bold">{{ __('What can we help you with?') }}</h1>
        <div class="grid gap-4 {{ $big ? 'sm:grid-cols-2' : '' }}">
            @forelse ($services as $service)
                <button wire:click="chooseService({{ $service->id }})" class="{{ $btn }} rounded-xl border-2 border-slate-200 bg-white text-left hover:border-[var(--brand)]" data-testid="service-{{ $service->id }}">
                    <span class="block font-semibold">{{ $service->name }}</span>
                    @if ($service->description) <span class="block text-base font-normal text-slate-500">{{ $service->description }}</span> @endif
                </button>
            @empty
                <p class="{{ $label }} text-slate-600">{{ __('No services are available for check-in right now. Please see the receptionist.') }}</p>
            @endforelse
        </div>
        <button wire:click="back" class="{{ $btn }} mt-6 rounded-xl">{{ __('Back') }}</button>
        @break

    @case('details')
        <h1 class="{{ $big ? 'text-4xl' : 'text-2xl' }} mb-2 font-bold">{{ __('Your details') }}</h1>
        <p class="{{ $label }} mb-6 text-slate-600">{{ $selectedService?->name }}</p>
        <form wire:submit="submit" class="space-y-5">
            <div>
                <label for="ci-name" class="{{ $label }} block font-medium">{{ __('Name') }}</label>
                <input id="ci-name" wire:model="name" autocomplete="name" class="{{ $input }} mt-1 w-full rounded-xl border border-slate-300">
                @error('name') <p class="mt-1 text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="ci-phone" class="{{ $label }} block font-medium">{{ $phoneRequired ? __('Mobile number') : __('Mobile number (optional)') }}</label>
                <input id="ci-phone" type="tel" inputmode="tel" wire:model="phone" autocomplete="tel" class="{{ $input }} mt-1 w-full rounded-xl border border-slate-300">
                @error('phone') <p class="mt-1 text-red-600">{{ $message }}</p> @enderror
            </div>
            <label class="{{ $label }} flex items-start gap-3">
                <input type="checkbox" wire:model="smsConsent" class="{{ $big ? 'h-8 w-8' : 'h-5 w-5' }} mt-1">
                <span>{{ __('Text me updates about my visit. Message and data rates may apply. Reply STOP to opt out.') }}</span>
            </label>
            @if ($departments->isNotEmpty())
                <div>
                    <label for="ci-dept" class="{{ $label }} block font-medium">{{ __('Department (optional)') }}</label>
                    <select id="ci-dept" wire:model="departmentId" class="{{ $input }} mt-1 w-full rounded-xl border border-slate-300">
                        <option value="">{{ __('Route me automatically') }}</option>
                        @foreach ($departments as $d) <option value="{{ $d->id }}">{{ $d->name }}</option> @endforeach
                    </select>
                </div>
            @endif
            <div class="flex gap-3">
                <button type="button" wire:click="back" class="{{ $btn }} rounded-xl border border-slate-300">{{ __('Back') }}</button>
                <button type="submit" class="{{ $btn }} flex-1 rounded-xl bg-[var(--brand)] font-semibold text-white">{{ __('Get my ticket') }}</button>
            </div>
        </form>
        @break

    @case('done')
        <div class="space-y-4 text-center" data-testid="ticket-confirmation">
            <p class="{{ $label }} text-slate-600">{{ $existing ? __('You are already checked in.') : __('You are checked in!') }}</p>
            <p class="{{ $big ? 'text-8xl' : 'text-6xl' }} font-black tracking-wider" data-testid="ticket-number">{{ $ticket?->number }}</p>
            <p class="{{ $label }}">{{ $ticket?->service->name }} · {{ $ticket?->department->name }}</p>
            @if ($estimate)
                <p class="{{ $label }}">{{ __('You are number :position in line.', ['position' => $estimate['position']]) }}</p>
                <p class="{{ $big ? 'text-3xl' : 'text-xl' }} font-semibold">{{ $estimate['label'] }}</p>
            @endif
            {{ $doneExtra ?? '' }}
        </div>
        @break
@endswitch
