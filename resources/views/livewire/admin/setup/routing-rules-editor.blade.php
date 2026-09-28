<div class="space-y-4">
    <p class="text-sm text-slate-600">{{ __('Rules are checked in order; the first matching active rule decides the department. Without a match, the service\'s default department at this location is used.') }}</p>

    <form wire:submit="save" class="grid gap-3 rounded-lg bg-white p-4 shadow-sm sm:grid-cols-4">
        <div>
            <label class="block text-sm font-medium" for="rr-service">{{ __('Service') }}</label>
            <select id="rr-service" wire:model="service_id" class="mt-1 w-full rounded border border-slate-300 px-2 py-2">
                <option value="">{{ __('Any service') }}</option>
                @foreach ($services as $s) <option value="{{ $s->id }}">{{ $s->name }}</option> @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium" for="rr-type">{{ __('Customer type') }}</label>
            <select id="rr-type" wire:model="customer_type" class="mt-1 w-full rounded border border-slate-300 px-2 py-2">
                <option value="">{{ __('Any') }}</option>
                <option value="walk_in">{{ __('Walk-in') }}</option>
                <option value="appointment">{{ __('Appointment') }}</option>
            </select>
        </div>
        <div class="sm:col-span-2">
            <span class="block text-sm font-medium">{{ __('Days (none = every day)') }}</span>
            <div class="mt-2 flex flex-wrap gap-3 text-sm">
                @foreach ($days as $num => $label)
                    <label class="flex items-center gap-1"><input type="checkbox" value="{{ $num }}" wire:model="weekdays"> {{ $label }}</label>
                @endforeach
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium">{{ __('Between (optional)') }}</label>
            <div class="mt-1 flex items-center gap-1">
                <input type="time" wire:model="starts_at" class="rounded border border-slate-300 px-2 py-1" aria-label="{{ __('From') }}">–
                <input type="time" wire:model="ends_at" class="rounded border border-slate-300 px-2 py-1" aria-label="{{ __('To') }}">
            </div>
            @error('ends_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium" for="rr-dept">{{ __('Route to department') }}</label>
            <select id="rr-dept" wire:model="department_id" class="mt-1 w-full rounded border border-slate-300 px-2 py-2">
                <option value="">—</option>
                @foreach ($departments as $d) <option value="{{ $d->id }}">{{ $d->name }}</option> @endforeach
            </select>
            @error('department_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="block text-sm font-medium" for="rr-prio">{{ __('Priority +/-') }}</label>
                <input id="rr-prio" type="number" wire:model="priority" class="mt-1 w-full rounded border border-slate-300 px-2 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium" for="rr-order">{{ __('Order') }}</label>
                <input id="rr-order" type="number" min="0" wire:model="sort_order" class="mt-1 w-full rounded border border-slate-300 px-2 py-2">
            </div>
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="rounded bg-slate-900 px-4 py-2 text-white">{{ $editingId ? __('Save') : __('Add rule') }}</button>
            @if ($editingId) <button type="button" wire:click="cancel" class="px-2 py-2 text-sm">{{ __('Cancel') }}</button> @endif
        </div>
    </form>

    <table class="min-w-full rounded-lg bg-white text-sm shadow-sm">
        <thead class="bg-slate-50 text-left text-slate-500">
            <tr><th class="px-3 py-2">#</th><th class="px-3 py-2">{{ __('When') }}</th><th class="px-3 py-2">{{ __('Route to') }}</th><th class="px-3 py-2">{{ __('Priority') }}</th><th></th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($rules as $rule)
                <tr @class(['opacity-50' => ! $rule->is_active])>
                    <td class="px-3 py-2">{{ $rule->sort_order }}</td>
                    <td class="px-3 py-2">
                        {{ $rule->service?->name ?? __('Any service') }}
                        · {{ $rule->customer_type ? \Illuminate\Support\Str::headline($rule->customer_type) : __('any customer') }}
                        · {{ $rule->weekdays ? collect($rule->weekdays)->map(fn ($d) => $days[$d])->join(', ') : __('every day') }}
                        @if ($rule->starts_at) · {{ substr($rule->starts_at, 0, 5) }}–{{ substr($rule->ends_at, 0, 5) }} @endif
                    </td>
                    <td class="px-3 py-2">{{ $rule->department->name }}</td>
                    <td class="px-3 py-2">{{ $rule->priority }}</td>
                    <td class="space-x-3 whitespace-nowrap px-3 py-2 text-right">
                        <button wire:click="edit({{ $rule->id }})" class="underline">{{ __('Edit') }}</button>
                        <button wire:click="setActive({{ $rule->id }}, {{ $rule->is_active ? 'false' : 'true' }})">{{ $rule->is_active ? __('Disable') : __('Enable') }}</button>
                        <button wire:click="delete({{ $rule->id }})" wire:confirm="{{ __('Delete this rule?') }}" class="text-red-600">{{ __('Delete') }}</button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-3 py-6 text-center text-slate-500">{{ __('No routing rules: services go to their default department.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
