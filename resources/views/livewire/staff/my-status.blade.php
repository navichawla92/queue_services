<div>
    @if ($employee && $location)
        <div class="flex flex-wrap items-center gap-4 rounded-lg bg-white p-4 shadow-sm" data-testid="my-status">
            <div>
                <div class="text-sm text-slate-500">{{ __('My status') }}</div>
                <div class="font-semibold">
                    {{ $employee->status->label() }}
                    @if ($employee->currentLocation && $employee->currentLocation->isNot($location))
                        <span class="text-sm font-normal text-amber-700">({{ __('at :location', ['location' => $employee->currentLocation->name]) }})</span>
                    @endif
                </div>
            </div>

            <div>
                <label for="my-desk" class="block text-sm text-slate-500">{{ __('Desk / room') }}</label>
                <select id="my-desk" wire:model.live="deskId" class="rounded border border-slate-300 px-2 py-1">
                    <option value="">—</option>
                    @foreach ($desks as $desk) <option value="{{ $desk->id }}">{{ $desk->label }}</option> @endforeach
                </select>
                @error('desk') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex gap-2">
                @foreach ($statuses as $status)
                    <button wire:click="setStatus('{{ $status->value }}')" @class([
                        'rounded px-3 py-2 text-sm',
                        'bg-slate-900 text-white' => $employee->status === $status,
                        'border border-slate-300' => $employee->status !== $status,
                    ])>{{ $status === \App\Domain\Organization\EmployeeStatus::Available && $employee->status === \App\Domain\Organization\EmployeeStatus::Offline ? __('Start shift') : $status->label() }}</button>
                @endforeach
            </div>
            @error('location') <p class="w-full text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
    @endif
</div>
