<?php

namespace App\Domain\Scheduling;

use App\Domain\Queue\Events\QueueChanged;
use App\Domain\Queue\Models\Ticket;
use App\Domain\Queue\TicketStatus;
use App\Domain\Scheduling\Models\Appointment;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;

/**
 * Keeps an appointment's status in step with its ticket (tasks 11.9):
 * started → In Service, completed → Completed, no-show → No-Show,
 * customer left the queue → Cancelled.
 */
class AppointmentTicketSync
{
    public function __construct(private readonly TenantContext $tenants) {}

    public function handle(QueueChanged $event): void
    {
        $tenant = $this->tenants->get()?->id === $event->tenantId ? $this->tenants->get() : Tenant::query()->find($event->tenantId);
        if ($tenant === null) {
            return;
        }

        $this->tenants->run($tenant, function () use ($event) {
            Ticket::query()->whereIn('id', $event->ticketIds())->whereNotNull('appointment_id')->get()
                ->each(function (Ticket $ticket) {
                    $status = match ($ticket->status) {
                        TicketStatus::InService => AppointmentStatus::InService,
                        TicketStatus::Completed => AppointmentStatus::Completed,
                        TicketStatus::NoShow => AppointmentStatus::NoShow, // customer count bumped by the ticket
                        TicketStatus::Cancelled => AppointmentStatus::Cancelled,
                        default => null,
                    };

                    if ($status !== null) {
                        Appointment::query()->whereKey($ticket->appointment_id)->where('status', '!=', $status->value)
                            ->get()->each(fn (Appointment $a) => $a->forceFill(['status' => $status])->save());
                    }
                });
        });
    }
}
