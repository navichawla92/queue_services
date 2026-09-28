{{-- Real-time staff queue. Pings on the private channel trigger a full re-render (resync). --}}
<div class="space-y-5"
     x-data="{
        online: false,
        poller: null,
        init() {
            this.tick = setInterval(() => this.$dispatch('tick'), 1000);
            if (!window.Echo) { this.goOffline(); return; }
            window.Echo.private(@js($channel)).listen('.queue.changed', () => $wire.$refresh());
            const conn = window.Echo.connector?.pusher?.connection;
            if (!conn) { this.goOffline(); return; }
            this.online = conn.state === 'connected';
            if (!this.online) this.goOffline();
            conn.bind('state_change', (s) => {
                const was = this.online;
                this.online = s.current === 'connected';
                if (this.online && !was) { clearInterval(this.poller); this.poller = null; $wire.$refresh(); }
                if (!this.online) this.goOffline();
            });
        },
        goOffline() { this.online = false; if (!this.poller) this.poller = setInterval(() => $wire.$refresh(), 5000); },
     }"
     data-testid="queue-dashboard">

    <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">{{ __('Queue') }} <span class="text-base font-normal text-slate-500">· {{ $location->name }}</span></h1>
        <div class="flex items-center gap-3">
            <span x-show="!online" class="rounded bg-amber-100 px-2 py-1 text-xs text-amber-800" data-testid="reconnecting">{{ __('Reconnecting… refreshing every 5 s') }}</span>
            <span class="text-xs text-slate-400">v{{ $version }}</span>
            @can('checkin.create')
                <a href="{{ route('staff.checkin') }}" class="rounded border border-slate-300 px-3 py-2 text-sm">{{ __('Check in customer') }}</a>
            @endcan
            @if ($canServe && $me)
                <button wire:click="callNext" class="rounded bg-green-600 px-5 py-2 font-semibold text-white" data-testid="call-next">{{ __('Call next') }}</button>
            @endif
        </div>
    </div>

    <livewire:staff.my-status />

    @if ($flash) <p class="rounded bg-green-50 p-3 text-green-800" data-testid="flash">{{ $flash }}</p> @endif
    @if ($error) <p class="rounded bg-red-50 p-3 text-red-800" role="alert" data-testid="error">{{ $error }}</p> @endif

    {{-- Filters --}}
    <div class="flex flex-wrap items-center gap-3 rounded-lg bg-white p-3 text-sm shadow-sm">
        <label class="flex items-center gap-1"><input type="checkbox" wire:model.live="mine"> {{ __('My queue') }}</label>
        <select wire:model.live="filterDepartment" class="rounded border border-slate-300 px-2 py-1" aria-label="{{ __('Department') }}">
            <option value="">{{ __('All departments') }}</option>
            @foreach ($departments as $d) <option value="{{ $d->id }}">{{ $d->name }}</option> @endforeach
        </select>
        <select wire:model.live="filterService" class="rounded border border-slate-300 px-2 py-1" aria-label="{{ __('Service') }}">
            <option value="">{{ __('All services') }}</option>
            @foreach ($services as $s) <option value="{{ $s->id }}">{{ $s->name }}</option> @endforeach
        </select>
        <select wire:model.live="filterStatus" class="rounded border border-slate-300 px-2 py-1" aria-label="{{ __('Status') }}">
            <option value="">{{ __('All statuses') }}</option>
            @foreach (['waiting', 'called', 'in_service', 'on_hold'] as $st) <option value="{{ $st }}">{{ \App\Domain\Queue\TicketStatus::from($st)->label() }}</option> @endforeach
        </select>
        <select wire:model.live="filterEmployee" class="rounded border border-slate-300 px-2 py-1" aria-label="{{ __('Employee') }}">
            <option value="">{{ __('Any employee') }}</option>
            @foreach ($employees as $e) <option value="{{ $e->id }}">{{ $e->display_name }}</option> @endforeach
        </select>
        <select wire:model.live="filterType" class="rounded border border-slate-300 px-2 py-1" aria-label="{{ __('Customer type') }}">
            <option value="">{{ __('Walk-ins & appointments') }}</option>
            <option value="walk_in">{{ __('Walk-ins') }}</option>
            <option value="appointment">{{ __('Appointments') }}</option>
        </select>
    </div>

    @foreach ([[__('Now serving'), $serving], [__('Waiting'), $waiting], [__('On hold'), $held]] as [$title, $rows])
        <section>
            <h2 class="mb-2 font-semibold">{{ $title }} <span class="text-slate-400">({{ $rows->count() }})</span></h2>
            <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-3 py-2">{{ __('Ticket') }}</th>
                            <th class="px-3 py-2">{{ __('Customer') }}</th>
                            <th class="px-3 py-2">{{ __('Service / department') }}</th>
                            <th class="px-3 py-2">{{ __('Checked in') }}</th>
                            <th class="px-3 py-2">{{ __('Waiting') }}</th>
                            <th class="px-3 py-2">{{ __('Employee') }}</th>
                            <th class="px-3 py-2">{{ __('Status') }}</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rows as $t)
                            @php
                                $isMine = $me && $t->serving_employee_id === $me->id;
                                $canAct = $canManage || $isMine;
                            @endphp
                            <tr wire:key="t-{{ $t->id }}" data-testid="ticket-{{ $t->number }}" @class(['bg-green-50' => $isMine])>
                                <td class="whitespace-nowrap px-3 py-2">
                                    <span class="font-mono text-base font-bold">{{ $t->number }}</span>
                                    @if ($t->priority > 0) <span class="ml-1 text-xs text-amber-700" title="{{ __('Priority') }}">▲{{ $t->priority }}</span> @endif
                                </td>
                                <td class="px-3 py-2">
                                    {{ $t->customer_name }}
                                    <span @class(['ml-1 rounded px-1.5 py-0.5 text-xs', 'bg-purple-100 text-purple-800' => $t->customer_type->value === 'appointment', 'bg-slate-100 text-slate-600' => $t->customer_type->value !== 'appointment'])>
                                        {{ $t->customer_type->value === 'appointment' ? __('Appt') : __('Walk-in') }}
                                    </span>
                                    @if ($t->notes_count) <span class="ml-1 text-xs" title="{{ __('Has notes') }}">📝{{ $t->notes_count }}</span> @endif
                                </td>
                                <td class="px-3 py-2">
                                    {{ $t->service->name }}
                                    <div class="flex items-center gap-1 text-xs text-slate-500"><span class="inline-block h-2 w-2 rounded-full" style="background: {{ $t->department->color }}"></span>{{ $t->department->name }}</div>
                                </td>
                                <td class="whitespace-nowrap px-3 py-2">{{ $t->checked_in_at->setTimezone($timezone)->format('H:i') }}</td>
                                <td class="whitespace-nowrap px-3 py-2 font-mono"
                                    x-data="{ s: {{ $t->currentWaitSeconds() }}, live: {{ $t->status->value === 'waiting' ? 'true' : 'false' }} }"
                                    @tick.window="if (live) s++"
                                    x-text="Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0')">{{ intdiv($t->currentWaitSeconds(), 60) }}:{{ str_pad($t->currentWaitSeconds() % 60, 2, '0', STR_PAD_LEFT) }}</td>
                                <td class="px-3 py-2">
                                    {{ $t->servingEmployee?->display_name ?? ($t->assignedEmployee ? '→ '.$t->assignedEmployee->display_name : '—') }}
                                    @if ($t->desk) <div class="text-xs text-slate-500">{{ $t->desk->label }}</div> @endif
                                </td>
                                <td class="px-3 py-2">{{ $t->status->label() }}@if ($t->hold_reason) <div class="text-xs text-slate-500">{{ $t->hold_reason }}</div> @endif</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        @switch($t->status->value)
                                            @case('waiting')
                                                @if ($canManage || $canServe) <button wire:click="openDialog('call', {{ $t->id }})" class="underline">{{ __('Call') }}</button> @endif
                                                @if ($canManage)
                                                    <button wire:click="openDialog('assign', {{ $t->id }})" class="underline">{{ __('Assign') }}</button>
                                                    <button wire:click="openDialog('transfer', {{ $t->id }})" class="underline">{{ __('Transfer') }}</button>
                                                    <button wire:click="openDialog('hold', {{ $t->id }})" class="underline">{{ __('Hold') }}</button>
                                                    <button wire:click="noShow({{ $t->id }})" wire:confirm="{{ __('Mark as no-show?') }}" class="text-red-600 underline">{{ __('No-show') }}</button>
                                                @endif
                                                @break
                                            @case('called')
                                                @if ($canAct)
                                                    <button wire:click="start({{ $t->id }})" class="rounded bg-slate-900 px-2 py-1 text-white">{{ __('Start') }}</button>
                                                    <button wire:click="recall({{ $t->id }})" class="underline">{{ __('Recall') }}</button>
                                                    <button wire:click="requeue({{ $t->id }})" class="underline">{{ __('Back to queue') }}</button>
                                                    <button wire:click="openDialog('transfer', {{ $t->id }})" class="underline">{{ __('Transfer') }}</button>
                                                    <button wire:click="noShow({{ $t->id }})" wire:confirm="{{ __('Mark as no-show?') }}" class="text-red-600 underline">{{ __('No-show') }}</button>
                                                @endif
                                                @break
                                            @case('in_service')
                                                @if ($canAct)
                                                    <button wire:click="openDialog('complete', {{ $t->id }})" class="rounded bg-slate-900 px-2 py-1 text-white">{{ __('Complete') }}</button>
                                                    <button wire:click="openDialog('hold', {{ $t->id }})" class="underline">{{ __('Hold') }}</button>
                                                    <button wire:click="openDialog('transfer', {{ $t->id }})" class="underline">{{ __('Transfer') }}</button>
                                                @endif
                                                @break
                                            @case('on_hold')
                                                @if ($canManage) <button wire:click="release({{ $t->id }})" class="underline">{{ __('Release') }}</button> @endif
                                                @break
                                        @endswitch
                                        <button wire:click="openDialog('note', {{ $t->id }})" class="underline">{{ __('Notes') }}</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-3 py-4 text-center text-slate-400">{{ __('None') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach

    <section data-testid="todays-appointments">
        <div class="mb-2 flex items-center justify-between">
            <h2 class="font-semibold">{{ __("Today's appointments") }} <span class="text-slate-400">({{ $appointments->count() }})</span></h2>
            @can('appointments.manage') <a href="{{ route('staff.appointments') }}" class="text-sm underline">{{ __('Manage') }}</a> @endcan
        </div>
        <div class="flex flex-wrap gap-2 text-sm">
            @forelse ($appointments as $a)
                <span @class(['rounded px-3 py-1 shadow-sm', 'bg-purple-100' => $a->status->value === 'arrived', 'bg-white' => $a->status->value !== 'arrived'])>
                    <span class="font-mono">{{ $a->starts_at->setTimezone($timezone)->format('H:i') }}</span>
                    {{ $a->customer_name }} · {{ $a->service->name }}{{ $a->employee ? ' · '.$a->employee->display_name : '' }}
                    · <em>{{ $a->status->label() }}</em>
                </span>
            @empty
                <span class="text-slate-400">{{ __('No more appointments today.') }}</span>
            @endforelse
        </div>
    </section>

    <section>
        <h2 class="mb-2 font-semibold">{{ __('Staff on shift') }}</h2>
        <div class="flex flex-wrap gap-2 text-sm">
            @forelse ($staff as $s)
                <span class="rounded bg-white px-3 py-1 shadow-sm">{{ $s->display_name }} · {{ $s->status->label() }}{{ $s->currentDesk ? ' · '.$s->currentDesk->label : '' }}</span>
            @empty
                <span class="text-slate-400">{{ __('No one is on shift.') }}</span>
            @endforelse
        </div>
    </section>

    {{-- Action dialog --}}
    @if ($dialog && $dialogTicket)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" wire:keydown.escape="closeDialog">
            <form wire:submit="confirmDialog" class="w-full max-w-lg space-y-4 rounded-lg bg-white p-6 shadow-xl" data-testid="dialog">
                <h3 class="text-lg font-semibold">
                    {{ match ($dialog) { 'transfer' => __('Transfer'), 'hold' => __('Place on hold'), 'note' => __('Internal notes'), 'complete' => __('Complete service'), 'assign' => __('Assign'), 'call' => __('Call customer'), default => '' } }}
                    · {{ $dialogTicket->number }}
                </h3>

                @if (in_array($dialog, ['transfer'], true))
                    <select wire:model="targetDepartmentId" class="w-full rounded border border-slate-300 px-2 py-2" aria-label="{{ __('Department') }}">
                        <option value="">{{ __('Same department') }}</option>
                        @foreach ($departments as $d) <option value="{{ $d->id }}">{{ $d->name }}</option> @endforeach
                    </select>
                @endif
                @if (in_array($dialog, ['transfer', 'assign'], true) || ($dialog === 'call' && $canManage))
                    <select wire:model="targetEmployeeId" class="w-full rounded border border-slate-300 px-2 py-2" aria-label="{{ __('Employee') }}">
                        <option value="">{{ $dialog === 'call' ? __('Me') : __('Anyone eligible') }}</option>
                        @foreach ($employees as $e) <option value="{{ $e->id }}">{{ $e->display_name }}</option> @endforeach
                    </select>
                @endif
                @if ($dialog === 'note')
                    <ul class="max-h-48 space-y-2 overflow-y-auto text-sm">
                        @forelse ($dialogTicket->notes as $n)
                            <li class="rounded bg-slate-50 p-2"><span class="text-slate-500">{{ $n->author->name }} · {{ $n->created_at->setTimezone($timezone)->format('H:i') }}</span><br>{{ $n->body }}</li>
                        @empty
                            <li class="text-slate-400">{{ __('No notes yet.') }}</li>
                        @endforelse
                    </ul>
                @endif
                @if (in_array($dialog, ['transfer', 'hold', 'note', 'complete'], true))
                    <textarea wire:model="text" rows="3" class="w-full rounded border border-slate-300 px-2 py-2"
                              placeholder="{{ match ($dialog) { 'hold' => __('Reason (optional)'), 'complete' => __('Outcome (optional)'), 'transfer' => __('Note for the next employee (optional)'), default => __('Internal note — never shown to customers') } }}"></textarea>
                    @error('text') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                @endif
                @if ($error) <p class="text-sm text-red-600">{{ $error }}</p> @endif

                <div class="flex justify-end gap-3">
                    <button type="button" wire:click="closeDialog" class="px-4 py-2">{{ __('Cancel') }}</button>
                    <button type="submit" class="rounded bg-slate-900 px-4 py-2 text-white">{{ $dialog === 'note' ? __('Add note') : __('Confirm') }}</button>
                </div>
            </form>
        </div>
    @endif
</div>
