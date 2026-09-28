<form wire:submit="save" class="space-y-4">
    @if ($departments->isEmpty())
        <p class="rounded bg-amber-50 p-4 text-sm text-amber-800">{{ __('Add a department first: each offered service is routed to a department of this location.') }}</p>
    @endif

    <table class="min-w-full rounded-lg bg-white text-sm shadow-sm">
        <thead class="bg-slate-50 text-left text-slate-500">
            <tr>
                <th class="px-3 py-2">{{ __('Service') }}</th>
                <th class="px-3 py-2">{{ __('Offered here → default department') }}</th>
                <th class="px-3 py-2">{{ __('Walk-in / Appointment') }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($services as $service)
                <tr>
                    <td class="px-3 py-2">
                        <span class="font-medium">{{ $service->name }}</span>
                        @unless ($service->customer_selectable) <span class="ml-1 text-xs text-slate-500">({{ __('staff only') }})</span> @endunless
                    </td>
                    <td class="px-3 py-2">
                        <select wire:model="offered.{{ $service->id }}" class="rounded border border-slate-300 px-2 py-1">
                            <option value="">{{ __('Not offered') }}</option>
                            @foreach ($departments as $d) <option value="{{ $d->id }}">{{ $d->name }}</option> @endforeach
                        </select>
                        @error('offered.'.$service->id) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </td>
                    <td class="px-3 py-2 text-slate-600">
                        {{ $service->allow_walk_in ? __('Walk-in') : '' }}{{ $service->allow_walk_in && $service->allow_appointment ? ' · ' : '' }}{{ $service->allow_appointment ? __('Appointment') : '' }}
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" class="px-3 py-6 text-center text-slate-500">{{ __('No services in the catalog yet.') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="flex items-center gap-3">
        <button type="submit" class="rounded bg-slate-900 px-4 py-2 text-white">{{ __('Save') }}</button>
        @if ($saved) <span class="text-sm text-green-700">{{ __('Saved.') }}</span> @endif
    </div>
</form>
