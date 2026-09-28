<?php

namespace App\Domain\Queue\Broadcasting;

use App\Domain\Display\DisplayChanged;
use App\Domain\Display\DisplayNotifier;
use App\Domain\Organization\Models\Location;
use App\Domain\Queue\Events\QueueChanged;
use App\Domain\Queue\Models\Ticket;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;

/**
 * Fans a committed queue change out to the real-time channels: customer
 * status pages here; staff dashboards and lobby displays are added by their
 * own publishers (see QueueServiceProvider).
 */
class BroadcastQueueChanges
{
    public function __construct(
        private readonly LiveUpdates $live,
        private readonly TenantContext $tenants,
    ) {}

    public function handle(QueueChanged $event): void
    {
        $tenant = $this->tenants->get()?->id === $event->tenantId
            ? $this->tenants->get()
            : Tenant::query()->find($event->tenantId);
        if ($tenant === null) {
            return;
        }

        $this->tenants->run($tenant, function () use ($event) {
            $location = Location::query()->find($event->locationId);
            if ($location === null) {
                return;
            }

            $this->live->publish(new StaffQueueChanged($event->tenantId, $location->id, $event->version, $event->changes));
            $this->live->publish(new LocationPulse($location->checkin_public_id, $event->version));
            app(DisplayNotifier::class)->location($location->id, DisplayChanged::QUEUE, $event->version);

            Ticket::query()->with('desk')->whereIn('id', $event->ticketIds())->get()
                ->each(fn (Ticket $ticket) => $this->live->publish(new TicketUpdated($ticket, $event->version)));
        });
    }
}
