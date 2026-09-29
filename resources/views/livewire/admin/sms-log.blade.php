<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h1 class="page-title">{{ __('SMS message log') }}</h1>
        <a href="{{ route('admin.sms') }}" class="link">{{ __('SMS settings') }}</a>
    </div>

    <div class="flex flex-wrap gap-3 card p-3 text-sm">
        <select wire:model.live="status" class="input input-sm" aria-label="{{ __('Status') }}">
            <option value="">{{ __('All statuses') }}</option>
            @foreach ($statuses as $s) <option value="{{ $s }}">{{ ucfirst($s) }}</option> @endforeach
        </select>
        <select wire:model.live="event" class="input input-sm" aria-label="{{ __('Event') }}">
            <option value="">{{ __('All events') }}</option>
            @foreach ($events as $e) <option value="{{ $e->value }}">{{ $e->label() }}</option> @endforeach
        </select>
        <label>{{ __('From') }} <input type="date" wire:model.live="from" class="input input-sm"></label>
        <label>{{ __('To') }} <input type="date" wire:model.live="to" class="input input-sm"></label>
    </div>

    <div class="overflow-x-auto card">
        <table class="data-table">
            <thead>
                <tr>
                    <th class="px-3 py-2">{{ __('When (UTC)') }}</th>
                    <th class="px-3 py-2">{{ __('To') }}</th>
                    <th class="px-3 py-2">{{ __('Event') }}</th>
                    <th class="px-3 py-2">{{ __('Message') }}</th>
                    <th class="px-3 py-2">{{ __('Status') }}</th>
                    <th class="px-3 py-2">{{ __('Segments') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($messages as $m)
                    <tr class="align-top">
                        <td class="whitespace-nowrap px-3 py-2">{{ $m->created_at->format('Y-m-d H:i') }}
                            @if ($m->scheduled_for && $m->status === 'queued') <div class="text-xs text-slate-500">{{ __('scheduled :at', ['at' => $m->scheduled_for->format('m-d H:i')]) }}</div> @endif
                        </td>
                        <td class="whitespace-nowrap px-3 py-2 font-mono">{{ $m->to }}</td>
                        <td class="px-3 py-2">{{ $m->event->label() }}@if ($m->ticket) <div class="text-xs text-slate-500">{{ $m->ticket->number }} · {{ $m->location?->name }}</div> @endif</td>
                        <td class="max-w-md px-3 py-2">{{ $m->body }}</td>
                        <td class="px-3 py-2">
                            <span @class(['rounded px-2 py-0.5 text-xs',
                                'bg-green-100 text-green-800' => in_array($m->status, ['delivered', 'sent']),
                                'bg-red-100 text-red-800' => in_array($m->status, ['failed', 'undelivered']),
                                'bg-slate-100 text-slate-700' => in_array($m->status, ['queued', 'suppressed', 'skipped']),
                            ])>{{ $m->status }}</span>
                            @if ($m->status_reason) <div class="text-xs text-slate-500">{{ $m->status_reason }}</div> @endif
                        </td>
                        <td class="px-3 py-2">{{ $m->segments }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-3 py-6 text-center text-slate-500">{{ __('No messages.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $messages->links() }}
</div>
