<div class="max-w-4xl space-y-6">
    <h1 class="text-2xl font-semibold">{{ __('Availability') }} · {{ $employee->display_name }}</h1>
    @if ($saved) <p class="rounded bg-green-50 p-3 text-green-800">{{ $saved }}</p> @endif

    <form wire:submit="saveSchedule" class="space-y-3 rounded-lg bg-white p-6 shadow-sm">
        <h2 class="font-semibold">{{ __('Weekly working hours (bookable for appointments)') }}</h2>
        @forelse ($rows as $i => $row)
            <div class="flex flex-wrap items-start gap-2" wire:key="sched-{{ $i }}">
                <select wire:model="rows.{{ $i }}.location_id" class="rounded border border-slate-300 px-2 py-1" aria-label="{{ __('Location') }}">
                    @foreach ($locations as $loc) <option value="{{ $loc->id }}">{{ $loc->name }}</option> @endforeach
                </select>
                <select wire:model="rows.{{ $i }}.weekday" class="rounded border border-slate-300 px-2 py-1" aria-label="{{ __('Day') }}">
                    @foreach ($weekdays as $n => $d) <option value="{{ $n }}">{{ $d }}</option> @endforeach
                </select>
                <input type="time" wire:model="rows.{{ $i }}.starts_at" class="rounded border border-slate-300 px-2 py-1" aria-label="{{ __('From') }}">
                <span class="py-1">–</span>
                <div>
                    <input type="time" wire:model="rows.{{ $i }}.ends_at" class="rounded border border-slate-300 px-2 py-1" aria-label="{{ __('To') }}">
                    @error('rows.'.$i.'.ends_at') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <button type="button" wire:click="removeRow({{ $i }})" class="py-1 text-sm text-red-600">{{ __('Remove') }}</button>
            </div>
        @empty
            <p class="text-sm text-slate-500">{{ __('No working hours: this person cannot be booked.') }}</p>
        @endforelse
        <div class="flex gap-3">
            <button type="button" wire:click="addRow" class="rounded border border-slate-300 px-3 py-1 text-sm">{{ __('Add hours') }}</button>
            <button type="submit" class="rounded bg-slate-900 px-4 py-2 text-white">{{ __('Save hours') }}</button>
        </div>
    </form>

    <div class="space-y-3 rounded-lg bg-white p-6 shadow-sm">
        <h2 class="font-semibold">{{ __('Time off') }} <span class="text-sm font-normal text-slate-500">({{ $tz }})</span></h2>
        <form wire:submit="addTimeOff" class="flex flex-wrap items-end gap-2">
            <label class="text-sm">{{ __('From') }} <input type="datetime-local" wire:model="offStart" class="block rounded border border-slate-300 px-2 py-1"></label>
            <label class="text-sm">{{ __('To') }} <input type="datetime-local" wire:model="offEnd" class="block rounded border border-slate-300 px-2 py-1"></label>
            <label class="text-sm">{{ __('Reason') }} <input wire:model="offReason" class="block rounded border border-slate-300 px-2 py-1"></label>
            <button type="submit" class="rounded bg-slate-900 px-3 py-2 text-white">{{ __('Add') }}</button>
        </form>
        @error('offEnd') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        <ul class="divide-y divide-slate-100 text-sm">
            @forelse ($timeOff as $t)
                <li class="flex justify-between py-2">
                    <span>{{ $t->starts_at->setTimezone($tz)->isoFormat('ddd MMM D, HH:mm') }} – {{ $t->ends_at->setTimezone($tz)->isoFormat('ddd MMM D, HH:mm') }} {{ $t->reason ? '· '.$t->reason : '' }}</span>
                    <button wire:click="removeTimeOff({{ $t->id }})" class="text-red-600">{{ __('Remove') }}</button>
                </li>
            @empty
                <li class="py-2 text-slate-500">{{ __('No upcoming time off.') }}</li>
            @endforelse
        </ul>
    </div>
</div>
