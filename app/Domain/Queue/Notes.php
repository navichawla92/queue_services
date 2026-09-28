<?php

namespace App\Domain\Queue;

use App\Domain\Access\AuditLogger;
use App\Domain\Queue\Models\Customer;
use App\Domain\Queue\Models\Note;
use App\Domain\Queue\Models\Ticket;
use App\Models\User;

/** Internal staff notes on tickets and customers (staff-queue "Internal notes"). */
class Notes
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly QueueLock $lock,
    ) {}

    public function addToTicket(Ticket $ticket, User $author, string $body, bool $alsoOnCustomer = false): Note
    {
        $note = Note::create([
            'ticket_id' => $ticket->id,
            'customer_id' => $alsoOnCustomer ? $ticket->customer_id : null,
            'author_id' => $author->id,
            'body' => $body,
        ]);

        $this->audit->log('note.added', $ticket, after: ['note_id' => $note->id], locationId: $ticket->location_id);

        // Dashboards show a notes indicator; announce it like any queue change.
        $this->lock->run($ticket->location, fn () => $this->lock->changed($ticket->location_id, 'note_added', $ticket->id));

        return $note;
    }

    public function addToCustomer(Customer $customer, User $author, string $body): Note
    {
        $note = Note::create(['customer_id' => $customer->id, 'author_id' => $author->id, 'body' => $body]);
        $this->audit->log('note.added', $customer, after: ['note_id' => $note->id]);

        return $note;
    }
}
