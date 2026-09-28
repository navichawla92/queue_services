<?php

namespace App\Livewire\PublicSite;

use App\Domain\Queue\Exceptions\QueueActionException;
use App\Domain\Queue\Models\Ticket;
use App\Domain\Queue\QueuePositions;
use App\Domain\Queue\TicketStateMachine;
use App\Domain\Queue\TicketStatus;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Live ticket status at /t/{token} (customer-check-in "Live ticket status
 * page"): real-time via the ticket & location pulse channels, polling as a
 * fallback; the customer can leave the queue.
 */
#[Layout('layouts.public')]
class TicketStatusPage extends Component
{
    #[Locked]
    public int $ticketId;

    public ?string $error = null;

    /** @param  string  $ticket  the ticket's public token (tenant bound by middleware) */
    public function mount(string $ticket): void
    {
        $this->ticketId = Ticket::query()->where('public_token', $ticket)->firstOrFail()->id;
    }

    public function leaveQueue(TicketStateMachine $queue): void
    {
        try {
            $queue->cancelByCustomer($this->ticket());
        } catch (QueueActionException) {
            $this->error = __('You can no longer leave the queue online. Please speak to a member of staff.');
        }
    }

    private function ticket(): Ticket
    {
        return Ticket::query()->with('location', 'department', 'service', 'desk')->findOrFail($this->ticketId);
    }

    public function render(QueuePositions $positions)
    {
        $ticket = $this->ticket();

        return view('livewire.public-site.ticket-status', [
            'ticket' => $ticket,
            'estimate' => $ticket->status === TicketStatus::Waiting ? $positions->estimate($ticket) : null,
        ])->title(__('Ticket :number', ['number' => $ticket->number]));
    }
}
