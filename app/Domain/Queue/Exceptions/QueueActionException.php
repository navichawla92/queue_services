<?php

namespace App\Domain\Queue\Exceptions;

use App\Domain\Queue\Models\Ticket;
use App\Domain\Queue\TicketStatus;
use RuntimeException;

/** A queue action that is not allowed right now; the message is user-facing. */
class QueueActionException extends RuntimeException
{
    public static function invalidTransition(Ticket $ticket, TicketStatus $to): self
    {
        return new self(__('Ticket :number is :status and cannot be moved to :to.', [
            'number' => $ticket->number,
            'status' => $ticket->status->label(),
            'to' => $to->label(),
        ]));
    }
}
