<form wire:submit="save" class="grid gap-4 rounded-lg bg-white p-6 shadow-sm sm:grid-cols-2">
    <p class="text-sm text-slate-600 sm:col-span-2">{{ __('Public booking page:') }} <a href="{{ $bookingUrl }}" target="_blank" class="break-all underline">{{ $bookingUrl }}</a></p>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="booking_enabled"> {{ __('Accept online bookings') }}</label>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="booking_choose_employee"> {{ __('Customers may choose the employee') }}</label>
    @foreach ([
        'booking_lead_minutes' => __('Minimum notice (minutes)'),
        'booking_horizon_days' => __('Bookable up to (days ahead)'),
        'booking_buffer_minutes' => __('Buffer after each appointment (minutes)'),
        'booking_cutoff_minutes' => __('No online changes within (minutes of start)'),
        'appointment_grace_minutes' => __('Auto no-show after (minutes late)'),
        'appointment_capacity_per_hour' => __('Max appointments per hour (blank = no limit)'),
    ] as $field => $label)
        <div>
            <label class="block text-sm font-medium" for="bk-{{ $field }}">{{ $label }}</label>
            <input id="bk-{{ $field }}" type="number" min="0" wire:model="{{ $field }}" class="mt-1 w-full rounded border border-slate-300 px-2 py-2">
            @error($field) <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
    @endforeach
    <div class="flex items-center gap-3 sm:col-span-2">
        <button type="submit" class="rounded bg-slate-900 px-4 py-2 text-white">{{ __('Save') }}</button>
        @if ($saved) <span class="text-sm text-green-700">{{ __('Saved.') }}</span> @endif
    </div>
</form>
