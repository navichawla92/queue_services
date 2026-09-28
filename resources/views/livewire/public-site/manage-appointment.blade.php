<div class="space-y-4 rounded-2xl bg-white p-6 shadow-sm" data-testid="manage-appointment">
    <h1 class="text-2xl font-bold">{{ __('Your appointment') }}</h1>
    @if ($message) <p class="rounded bg-green-50 p-3 text-green-800">{{ $message }}</p> @endif
    @if ($error) <p class="rounded bg-amber-100 p-3 text-amber-900" role="alert">{{ $error }}</p> @endif

    <dl class="grid grid-cols-3 gap-2">
        <dt class="text-slate-500">{{ __('Service') }}</dt><dd class="col-span-2">{{ $appointment->service->name }}</dd>
        <dt class="text-slate-500">{{ __('When') }}</dt><dd class="col-span-2 font-semibold">{{ $appointment->localStart()->isoFormat('dddd, MMMM D · h:mm A') }}</dd>
        <dt class="text-slate-500">{{ __('Where') }}</dt><dd class="col-span-2">{{ $appointment->location->name }}{{ $appointment->location->address ? ', '.$appointment->location->address : '' }}</dd>
        <dt class="text-slate-500">{{ __('Code') }}</dt><dd class="col-span-2 font-mono">{{ $appointment->confirmation_code }}</dd>
        <dt class="text-slate-500">{{ __('Status') }}</dt><dd class="col-span-2">{{ $appointment->status->label() }}</dd>
    </dl>

    @if ($isToday && $appointment->status->isUpcoming())
        <a href="{{ $appointment->checkinUrl() }}" class="block rounded-xl bg-[var(--brand)] px-5 py-3 text-center font-semibold text-white">{{ __("I'm here — check in") }}</a>
    @endif

    @if ($canChange)
        @if ($rescheduling)
            <div class="space-y-3">
                <label class="block font-medium" for="m-date">{{ __('New date') }}</label>
                <input id="m-date" type="date" wire:model.live="date" class="rounded border border-slate-300 px-3 py-2">
                <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                    @forelse ($slots as $s)
                        <button wire:click="reschedule('{{ $s->key() }}')" class="rounded-lg border border-slate-200 px-2 py-2">{{ $s->start->setTimezone($tz)->isoFormat('h:mm A') }}</button>
                    @empty
                        <p class="col-span-4 text-slate-500">{{ __('No times available that day.') }}</p>
                    @endforelse
                </div>
            </div>
        @else
            <div class="flex gap-3">
                <button wire:click="startReschedule" class="rounded-xl border border-slate-300 px-5 py-3">{{ __('Reschedule') }}</button>
                <button wire:click="cancel" wire:confirm="{{ __('Cancel this appointment?') }}" class="rounded-xl border border-red-300 px-5 py-3 text-red-700">{{ __('Cancel appointment') }}</button>
            </div>
        @endif
    @elseif ($appointment->status->isUpcoming())
        <p class="text-slate-600">{{ __('It is too close to your appointment to change it online. Please call the location instead.') }}@if ($appointment->location->phone) {{ $appointment->location->phone }} @endif</p>
    @endif
</div>
