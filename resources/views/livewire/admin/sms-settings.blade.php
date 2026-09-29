<div class="space-y-8">
    <div class="flex items-center justify-between">
        <h1 class="page-title">{{ __('SMS notifications') }}</h1>
        <a href="{{ route('admin.sms.log') }}" class="link">{{ __('Message log') }}</a>
    </div>
    @if ($saved) <p class="alert-success">{{ $saved }}</p> @endif

    {{-- Provider --}}
    <form wire:submit="saveProvider" class="grid gap-4 card p-6 sm:grid-cols-3">
        <h2 class="text-lg font-semibold sm:col-span-3">{{ __('Provider & sender') }}</h2>
        <div>
            <label class="form-label" for="sms-provider">{{ __('Provider') }}</label>
            <select id="sms-provider" wire:model.live="provider" class="input mt-1 w-full">
                <option value="log">{{ __('Log only (no messages sent)') }}</option>
                <option value="twilio">Twilio</option>
            </select>
        </div>
        @if ($provider === 'twilio')
            <div>
                <label class="form-label" for="sms-sid">{{ __('Account SID') }}</label>
                <input id="sms-sid" wire:model="account_sid" class="input mt-1 w-full font-mono">
                @error('account_sid') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label" for="sms-token">{{ __('Auth token') }}</label>
                <input id="sms-token" type="password" wire:model="auth_token" autocomplete="off" placeholder="{{ $hasToken ? __('•••• stored — leave blank to keep') : '' }}" class="input mt-1 w-full font-mono">
            </div>
            <div>
                <label class="form-label" for="sms-from">{{ __('Sender number (E.164)') }}</label>
                <input id="sms-from" wire:model="from_number" placeholder="+12025550100" class="input mt-1 w-full font-mono">
                @error('from_number') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label" for="sms-mss">{{ __('…or Messaging Service SID') }}</label>
                <input id="sms-mss" wire:model="messaging_service_sid" class="input mt-1 w-full font-mono">
            </div>
            <div class="text-xs text-slate-500 sm:col-span-3">
                {{ __('Set these webhook URLs in Twilio: status callback is sent automatically; inbound messages (STOP/HELP):') }}
                <code class="break-all">{{ route('public.webhooks.sms.inbound', app(\App\Domain\Tenancy\TenantContext::class)->require()->public_id) }}</code>
            </div>
        @endif
        <div class="sm:col-span-3">
            <label class="form-label" for="sms-help">{{ __('Reply to HELP') }}</label>
            <input id="sms-help" wire:model="help_message" maxlength="320" class="input mt-1 w-full">
        </div>
        <div>
            <span class="form-label">{{ __('Quiet hours (reminders & feedback)') }}</span>
            <div class="mt-1 flex items-center gap-2">
                <input type="time" wire:model="quiet_start" class="input input-sm" aria-label="{{ __('From') }}">–
                <input type="time" wire:model="quiet_end" class="input input-sm" aria-label="{{ __('To') }}">
            </div>
        </div>
        <div>
            <label class="form-label" for="sms-thr">{{ __('Wait update when estimate changes by (min)') }}</label>
            <input id="sms-thr" type="number" min="1" wire:model="wait_update_threshold" class="input mt-1 w-full">
        </div>
        <div>
            <label class="form-label" for="sms-pos">{{ __('Position alert at position') }}</label>
            <input id="sms-pos" type="number" min="2" wire:model="position_alert_at" class="input mt-1 w-full">
        </div>
        @if ($locations->count())
            <div class="sm:col-span-3">
                <span class="form-label">{{ __('Per-location sender number (optional)') }}</span>
                <div class="mt-1 grid gap-2 sm:grid-cols-3">
                    @foreach ($locations as $loc)
                        <label class="text-sm">{{ $loc->name }}
                            <input wire:model="locationSenders.{{ $loc->id }}" placeholder="+1…" class="mt-1 w-full input input-sm font-mono">
                        </label>
                    @endforeach
                </div>
                @error('locationSenders.*') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        @endif
        <div class="sm:col-span-3"><button type="submit" class="btn btn-primary">{{ __('Save provider settings') }}</button></div>
    </form>

    {{-- Toggles --}}
    <form wire:submit="saveToggles" class="overflow-x-auto card p-6">
        <h2 class="mb-3 text-lg font-semibold">{{ __('Which messages are sent') }}</h2>
        <table class="data-table">
            <thead class="text-left text-slate-500">
                <tr>
                    <th class="py-2 pr-4">{{ __('Event') }}</th>
                    <th class="py-2 pr-4">{{ __('Company') }}</th>
                    @foreach ($locations as $loc) <th class="py-2 pr-4">{{ $loc->name }}</th> @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($events as $event)
                    <tr>
                        <td class="py-2 pr-4">{{ $event->label() }}</td>
                        <td class="py-2 pr-4"><input type="checkbox" wire:model="enabled.{{ $event->value }}" aria-label="{{ $event->label() }}"></td>
                        @foreach ($locations as $loc)
                            <td class="py-2 pr-4">
                                <select wire:model="overrides.{{ $loc->id }}.{{ $event->value }}" class="input px-1.5 py-0.5 text-xs">
                                    <option value="">{{ __('Inherit') }}</option>
                                    <option value="1">{{ __('On') }}</option>
                                    <option value="0">{{ __('Off') }}</option>
                                </select>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
        <button type="submit" class="mt-4 btn btn-primary">{{ __('Save toggles') }}</button>
    </form>

    {{-- Templates --}}
    <form wire:submit="saveTemplate" class="grid gap-4 card p-6 lg:grid-cols-2">
        <h2 class="text-lg font-semibold lg:col-span-2">{{ __('Message templates') }}</h2>
        <div class="space-y-3">
            <div class="flex gap-2">
                <select wire:model.live="templateEvent" class="flex-1 input" aria-label="{{ __('Event') }}">
                    @foreach ($events as $event) <option value="{{ $event->value }}">{{ $event->label() }}</option> @endforeach
                </select>
                <select wire:model.live="templateLocale" class="input" aria-label="{{ __('Language') }}">
                    <option value="en">EN</option>
                    <option value="es">ES</option>
                </select>
            </div>
            <textarea wire:model.live.debounce.400ms="templateBody" rows="6" class="w-full input font-mono text-sm"></textarea>
            @error('templateBody') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            <p class="text-xs text-slate-500">{{ __('Placeholders:') }} @foreach ($placeholders as $tag) <code>{{ $tag }}</code> @endforeach</p>
            <div class="flex gap-3">
                <button type="submit" class="btn btn-primary">{{ __('Save template') }}</button>
                <button type="button" wire:click="resetTemplate" class="btn btn-ghost">{{ __('Restore default') }}</button>
            </div>
        </div>
        <div>
            <span class="form-label">{{ __('Preview') }}</span>
            <div class="mt-1 max-w-sm rounded-2xl bg-slate-100 p-4 text-sm whitespace-pre-wrap" data-testid="template-preview">{{ $preview }}</div>
            <p class="mt-2 text-xs text-slate-500" data-testid="segment-count">{{ trans_choice(':chars characters · :count segment|:chars characters · :count segments', $segments, ['chars' => $chars, 'count' => $segments]) }}</p>
        </div>
    </form>
</div>
