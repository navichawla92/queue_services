<div class="max-w-2xl space-y-4">
    <h1 class="text-2xl font-semibold">{{ __('Check in a customer') }} <span class="text-base font-normal text-slate-500">· {{ $location->name }}</span></h1>

    @if ($closed)
        <p class="rounded bg-amber-50 p-3 text-sm text-amber-800">{{ __('Walk-ins are currently closed at this location. You can still check customers in.') }}</p>
    @endif
    @if ($result)
        <p class="rounded bg-green-50 p-3 text-green-800" data-testid="checkin-result">{{ $result }}</p>
    @endif

    <form wire:submit="save" class="grid gap-4 rounded-lg bg-white p-6 shadow-sm sm:grid-cols-2">
        <div>
            <label class="block text-sm font-medium" for="rc-name">{{ __('Customer name') }}</label>
            <input id="rc-name" wire:model="name" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium" for="rc-phone">{{ __('Mobile number (optional)') }}</label>
            <input id="rc-phone" type="tel" wire:model="phone" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
            @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            <label class="mt-2 flex items-center gap-2 text-sm"><input type="checkbox" wire:model="smsConsent"> {{ __('Customer agrees to SMS updates') }}</label>
        </div>
        <div>
            <label class="block text-sm font-medium" for="rc-service">{{ __('Service') }}</label>
            <select id="rc-service" wire:model="serviceId" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
                <option value="">—</option>
                @foreach ($services as $s) <option value="{{ $s->id }}">{{ $s->name }}{{ $s->customer_selectable ? '' : ' ('.__('staff only').')' }}</option> @endforeach
            </select>
            @error('serviceId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium" for="rc-dept">{{ __('Department') }}</label>
            <select id="rc-dept" wire:model="departmentId" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
                <option value="">{{ __('Route automatically') }}</option>
                @foreach ($departments as $d) <option value="{{ $d->id }}">{{ $d->name }}</option> @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium" for="rc-emp">{{ __('Assign to (optional)') }}</label>
            <select id="rc-emp" wire:model="assignedEmployeeId" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
                <option value="">{{ __('Anyone eligible') }}</option>
                @foreach ($employees as $e) <option value="{{ $e->id }}">{{ $e->display_name }}</option> @endforeach
            </select>
        </div>
        <div class="flex items-end">
            <button type="submit" class="rounded bg-slate-900 px-4 py-2 text-white">{{ __('Issue ticket') }}</button>
        </div>
    </form>
</div>
