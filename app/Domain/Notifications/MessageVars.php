<?php

namespace App\Domain\Notifications;

use App\Domain\Queue\Models\Ticket;
use Illuminate\Support\Str;

/** Template placeholder values for a ticket. */
final class MessageVars
{
    /**
     * @param  array{position?: int, label?: string, minutes?: int|null}|null  $estimate
     * @return array<string, string|int|null>
     */
    public static function forTicket(Ticket $ticket, ?array $estimate = null): array
    {
        return [
            'first_name' => Str::before(trim($ticket->customer_name), ' '),
            'ticket_number' => $ticket->number,
            'service' => $ticket->service->name,
            'department' => $ticket->department->name,
            'location' => $ticket->location->name,
            'desk' => $ticket->desk_id !== null ? $ticket->desk->label : __('the front desk'),
            'wait' => ($estimate['minutes'] ?? null) !== null ? mb_strtolower((string) $estimate['label']) : __('not available yet'),
            'position' => $estimate['position'] ?? null,
            'status_link' => route('public.ticket', $ticket->public_token),
        ];
    }
}
