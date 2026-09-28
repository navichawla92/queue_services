<?php

namespace App\Domain\Notifications;

use App\Domain\Notifications\Models\SmsMessage;
use App\Domain\Organization\Models\Location;
use App\Domain\Queue\Events\QueueChanged;
use App\Domain\Queue\Models\Ticket;
use App\Domain\Queue\QueuePositions;
use App\Domain\Queue\TicketStatus;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;

/**
 * Turns committed queue changes into SMS (sms-notifications events): the
 * direct ones (check-in, called/recalled, transfer, desk change) plus a scan
 * of waiting tickets for "you're next", position and wait-time updates.
 */
class QueueNotifications
{
    public function __construct(
        private readonly TenantContext $tenants,
        private readonly NotificationDispatcher $dispatcher,
        private readonly NotificationPreferences $preferences,
        private readonly QueuePositions $positions,
        private readonly SmsConfig $config,
    ) {}

    public function handle(QueueChanged $event): void
    {
        $tenant = $this->tenants->get()?->id === $event->tenantId ? $this->tenants->get() : Tenant::query()->find($event->tenantId);
        if ($tenant === null || ! $tenant->isActive()) {
            return;
        }

        $this->tenants->run($tenant, function () use ($event) {
            $location = Location::query()->find($event->locationId);
            if ($location === null) {
                return;
            }

            foreach ($event->changes as $change) {
                $ticket = $change['ticket_id'] ? Ticket::query()->with(['location', 'department', 'service', 'desk'])->find($change['ticket_id']) : null;
                if ($ticket === null) {
                    continue;
                }

                $notification = match ($change['type']) {
                    'created' => NotificationEvent::CheckinConfirmation,
                    'called', 'recalled' => NotificationEvent::RepresentativeReady,
                    'transferred' => NotificationEvent::Transfer,
                    'desk_changed' => NotificationEvent::DeskChange,
                    default => null,
                };

                if ($notification) {
                    $this->send($notification, $ticket);
                }
            }

            // Tickets checked in by this very change already got their position in
            // the confirmation; alerts are for customers who moved up since.
            $justCreated = collect($event->changes)->where('type', 'created')->pluck('ticket_id')->filter()->all();
            $this->scanWaiting($location, $justCreated);
        });
    }

    /**
     * "You're next", position alert and significant wait changes, each once per ticket.
     *
     * @param  list<int>  $skipTicketIds
     */
    private function scanWaiting(Location $location, array $skipTicketIds = []): void
    {
        $wantNext = $this->preferences->enabled(NotificationEvent::YoureNext, $location->id);
        $wantPosition = $this->preferences->enabled(NotificationEvent::PositionUpdate, $location->id);
        $wantWait = $this->preferences->enabled(NotificationEvent::WaitUpdate, $location->id);
        if (! $wantNext && ! $wantPosition && ! $wantWait) {
            return;
        }

        $settings = $this->config->settings($location->id);

        Ticket::query()->with(['location', 'department', 'service', 'desk'])
            ->where('location_id', $location->id)
            ->where('status', TicketStatus::Waiting->value)
            ->where('sms_consent', true)
            ->whereNotNull('customer_phone')
            ->when($skipTicketIds !== [], fn ($q) => $q->whereNotIn('id', $skipTicketIds))
            ->each(function (Ticket $ticket) use ($wantNext, $wantPosition, $wantWait, $settings) {
                $estimate = $this->positions->estimate($ticket);

                $startedAt = $ticket->initial_ahead + 1;
                if ($wantNext && $estimate['position'] === 1 && $startedAt > 1) {
                    $this->sendOnce(NotificationEvent::YoureNext, $ticket, $estimate);
                } elseif ($wantPosition && $estimate['position'] === $settings->position_alert_at && $startedAt > $settings->position_alert_at) {
                    $this->sendOnce(NotificationEvent::PositionUpdate, $ticket, $estimate);
                }

                if ($wantWait && $estimate['minutes'] !== null) {
                    $last = $ticket->notified_wait_minutes ?? $ticket->estimated_wait_minutes;
                    if ($last === null || abs($estimate['minutes'] - $last) >= $settings->wait_update_threshold) {
                        $this->send(NotificationEvent::WaitUpdate, $ticket, $estimate);
                        $ticket->forceFill(['notified_wait_minutes' => $estimate['minutes']])->save();
                    }
                }
            });
    }

    /** @param  array{position: int, label: string}|null  $estimate */
    private function sendOnce(NotificationEvent $event, Ticket $ticket, ?array $estimate = null): void
    {
        $already = SmsMessage::query()->where('ticket_id', $ticket->id)->where('event', $event->value)->exists();
        if (! $already) {
            $this->send($event, $ticket, $estimate);
        }
    }

    /** @param  array{position: int, label: string}|null  $estimate */
    private function send(NotificationEvent $event, Ticket $ticket, ?array $estimate = null): void
    {
        $estimate ??= $ticket->status === TicketStatus::Waiting ? $this->positions->estimate($ticket) : null;

        $this->dispatcher->send(new SmsContext(
            event: $event,
            to: $ticket->customer_phone,
            consent: $ticket->sms_consent,
            locationId: $ticket->location_id,
            vars: MessageVars::forTicket($ticket, $estimate),
            ticketId: $ticket->id,
            customerId: $ticket->customer_id,
            locale: (string) $this->tenants->require()->settings()->get('locale', 'en'),
        ));
    }
}
