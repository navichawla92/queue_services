@php $status = $ticket->status; @endphp
<div wire:poll.5s
     x-data="{ online: true }"
     x-init="
        if (window.Echo) {
            window.Echo.channel('ticket.{{ $ticket->public_token }}').listen('.ticket.updated', () => $wire.$refresh());
            window.Echo.channel('location.{{ $ticket->location->checkin_public_id }}.pulse').listen('.queue.pulse', () => $wire.$refresh());
            const conn = window.Echo.connector?.pusher?.connection;
            conn?.bind('state_change', (s) => online = s.current === 'connected');
        }"
     class="space-y-4 rounded-2xl bg-white p-6 text-center shadow-sm" data-testid="ticket-status">

    <p class="text-slate-600">{{ $ticket->location->name }}</p>
    <p class="text-6xl font-black tracking-wider">{{ $ticket->number }}</p>
    <p class="text-slate-600">{{ $ticket->service->name }} · {{ $ticket->department->name }}</p>

    @switch($status)
        @case(\App\Domain\Queue\TicketStatus::Waiting)
            <p class="text-xl">{{ __('You are number :position in line.', ['position' => $estimate['position']]) }}</p>
            <p class="text-2xl font-semibold">{{ $estimate['label'] }}</p>
            @break
        @case(\App\Domain\Queue\TicketStatus::Called)
            <div class="rounded-xl bg-green-600 p-6 text-white" data-testid="called">
                <p class="text-3xl font-bold">{{ __("It's your turn!") }}</p>
                @if ($ticket->desk)
                    <p class="mt-2 text-2xl">{{ __('Please go to :desk', ['desk' => $ticket->desk->label]) }}</p>
                @endif
            </div>
            @break
        @case(\App\Domain\Queue\TicketStatus::InService)
            <p class="text-xl font-semibold">{{ __('You are being served.') }}</p>
            @break
        @case(\App\Domain\Queue\TicketStatus::OnHold)
            <p class="text-xl">{{ __('Your ticket is on hold. A member of staff will call you again.') }}</p>
            @break
        @case(\App\Domain\Queue\TicketStatus::Completed)
            <p class="text-xl font-semibold">{{ __('Thank you for your visit!') }}</p>
            @break
        @case(\App\Domain\Queue\TicketStatus::Cancelled)
            <p class="text-xl">{{ __('You have left the queue.') }}</p>
            @break
        @default
            <p class="text-xl">{{ __('This ticket is no longer active.') }}</p>
    @endswitch

    @if ($error) <p class="rounded bg-amber-100 p-3 text-amber-900">{{ $error }}</p> @endif

    @if ($status === \App\Domain\Queue\TicketStatus::Waiting)
        <button wire:click="leaveQueue" wire:confirm="{{ __('Leave the queue? You will lose your place.') }}"
                class="mt-4 rounded-xl border border-slate-300 px-5 py-3 text-slate-700">{{ __('Leave queue') }}</button>
    @endif

    <p x-show="!online" class="text-xs text-slate-400">{{ __('Reconnecting… this page still refreshes automatically.') }}</p>
</div>
