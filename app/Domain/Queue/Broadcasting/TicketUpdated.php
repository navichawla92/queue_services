<?php

namespace App\Domain\Queue\Broadcasting;

use App\Domain\Queue\Models\Ticket;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * To the customer's own status page. The channel name contains the ticket's
 * unguessable public token, so knowing it is the authorization. No names or
 * phone numbers in the payload.
 */
class TicketUpdated implements ShouldBroadcastNow
{
    /** @var array<string, mixed> */
    public array $ticket;

    private string $token;

    public function __construct(Ticket $ticket, public readonly int $version)
    {
        $this->ticket = [
            'number' => $ticket->number,
            'status' => $ticket->status->value,
            'desk' => $ticket->desk?->label,
        ];
        $this->token = $ticket->public_token;
    }

    public function broadcastOn(): Channel
    {
        return new Channel('ticket.'.$this->token);
    }

    public function broadcastAs(): string
    {
        return 'ticket.updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['version' => $this->version, 'ticket' => $this->ticket];
    }
}
