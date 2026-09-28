<div class="space-y-4 rounded-2xl bg-white p-6 shadow-sm" data-testid="booking">
    <h1 class="text-2xl font-bold">{{ __('Book an appointment') }}</h1>
    <p class="text-slate-600">{{ $location->name }}</p>

    @if ($error) <p class="rounded bg-amber-100 p-3 text-amber-900" role="alert">{{ $error }}</p> @endif

    @if (! $bookingOpen)
        <p>{{ __('Online booking is not available at this location. Please call us.') }}</p>
    @else
        @switch($step)
            @case('service')
                <h2 class="font-semibold">{{ __('Choose a service') }}</h2>
                <div class="grid gap-3">
                    @forelse ($services as $s)
                        <button wire:click="chooseService({{ $s->id }})" class="rounded-xl border-2 border-slate-200 px-4 py-3 text-left hover:border-[var(--brand)]">
                            <span class="block font-semibold">{{ $s->name }}</span>
                            <span class="block text-sm text-slate-500">{{ __(':min min', ['min' => $s->expected_minutes]) }}{{ $s->description ? ' · '.$s->description : '' }}</span>
                        </button>
                    @empty
                        <p class="text-slate-600">{{ __('No services can be booked online right now.') }}</p>
                    @endforelse
                </div>
                @break

            @case('employee')
                <h2 class="font-semibold">{{ __('With whom?') }}</h2>
                <div class="grid gap-3">
                    <button wire:click="chooseEmployee(null)" class="rounded-xl border-2 border-slate-200 px-4 py-3 text-left">{{ __('First available') }}</button>
                    @foreach ($employees as $e)
                        <button wire:click="chooseEmployee({{ $e->id }})" class="rounded-xl border-2 border-slate-200 px-4 py-3 text-left">{{ $e->display_name }}</button>
                    @endforeach
                </div>
                <button wire:click="back" class="text-sm underline">{{ __('Back') }}</button>
                @break

            @case('date')
                <h2 class="font-semibold">{{ $service?->name }}{{ $employee ? ' · '.$employee->display_name : '' }}</h2>
                @if ($days === [])
                    <p class="text-slate-600">{{ __('No times are available in the coming weeks. Please call us.') }}</p>
                @else
                    <div class="flex gap-2 overflow-x-auto pb-2">
                        @foreach ($days as $d)
                            <button wire:click="chooseDate('{{ $d->format('Y-m-d') }}')" @class(['min-w-20 rounded-lg border px-3 py-2 text-center', 'border-[var(--brand)] bg-[var(--brand)] text-white' => $date === $d->format('Y-m-d'), 'border-slate-200' => $date !== $d->format('Y-m-d')])>
                                <span class="block text-xs">{{ $d->locale(app()->getLocale())->isoFormat('ddd') }}</span>
                                <span class="block font-semibold">{{ $d->isoFormat('MMM D') }}</span>
                            </button>
                        @endforeach
                    </div>
                    <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                        @foreach ($slots as $s)
                            <button wire:click="chooseSlot('{{ $s->key() }}')" class="rounded-lg border border-slate-200 px-2 py-2 hover:border-[var(--brand)]" data-testid="slot">
                                {{ $s->start->setTimezone($tz)->isoFormat('h:mm A') }}
                            </button>
                        @endforeach
                    </div>
                @endif
                <button wire:click="back" class="text-sm underline">{{ __('Back') }}</button>
                @break

            @case('details')
                <p class="rounded bg-slate-50 p-3">{{ $service?->name }} · <strong>{{ $chosen?->isoFormat('dddd, MMMM D · h:mm A') }}</strong></p>
                <form wire:submit="submit" class="space-y-4">
                    <div>
                        <label for="b-name" class="block font-medium">{{ __('Name') }}</label>
                        <input id="b-name" wire:model="name" autocomplete="name" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3">
                        @error('name') <p class="text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="b-phone" class="block font-medium">{{ __('Mobile number') }}</label>
                        <input id="b-phone" type="tel" wire:model="phone" autocomplete="tel" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3">
                        @error('phone') <p class="text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="b-email" class="block font-medium">{{ __('Email (optional)') }}</label>
                        <input id="b-email" type="email" wire:model="email" autocomplete="email" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3">
                        @error('email') <p class="text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <label class="flex items-start gap-3">
                        <input type="checkbox" wire:model="smsConsent" class="mt-1 h-5 w-5">
                        <span>{{ __('Text me my confirmation and reminders. Message and data rates may apply. Reply STOP to opt out.') }}</span>
                    </label>
                    <div class="flex gap-3">
                        <button type="button" wire:click="back" class="rounded-xl border border-slate-300 px-5 py-3">{{ __('Back') }}</button>
                        <button type="submit" class="flex-1 rounded-xl bg-[var(--brand)] px-5 py-3 font-semibold text-white">{{ __('Book appointment') }}</button>
                    </div>
                </form>
                @break

            @case('done')
                <div class="space-y-3 text-center" data-testid="booking-confirmed">
                    <p class="text-xl font-semibold">{{ __('You are booked!') }}</p>
                    <p>{{ $appointment?->service->name }} · {{ $appointment?->localStart()->isoFormat('dddd, MMMM D · h:mm A') }}</p>
                    <p>{{ __('Confirmation code') }}: <strong class="font-mono text-2xl tracking-widest">{{ $appointment?->confirmation_code }}</strong></p>
                    <a href="{{ $appointment?->manageUrl() }}" class="inline-block underline">{{ __('Reschedule or cancel') }}</a>
                </div>
                @break
        @endswitch
    @endif
</div>
