<?php

namespace App\Domain\Feedback;

use App\Domain\Billing\Features;
use App\Domain\Feedback\Models\FeedbackRequest;
use App\Domain\Notifications\NotificationDispatcher;
use App\Domain\Notifications\NotificationEvent;
use App\Domain\Notifications\SmsContext;
use App\Domain\Queue\Events\QueueChanged;
use App\Domain\Queue\Models\Ticket;
use App\Domain\Queue\TicketStatus;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Support\Str;

/**
 * Sends the post-service feedback request (customer-feedback "Feedback
 * request after service"): once per visit, only with SMS consent, not
 * within the customer's cooldown, after the configured delay (quiet hours
 * are applied by the dispatcher).
 */
class FeedbackRequests
{
    public function __construct(
        private readonly TenantContext $tenants,
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    public function handle(QueueChanged $event): void
    {
        if (! $event->has('completed')) {
            return;
        }

        $tenant = $this->tenants->get()?->id === $event->tenantId ? $this->tenants->get() : Tenant::query()->find($event->tenantId);
        if ($tenant === null) {
            return;
        }

        $this->tenants->run($tenant, function () use ($event) {
            $ids = collect($event->changes)->where('type', 'completed')->pluck('ticket_id')->filter()->all();
            Ticket::query()->with('location.tenant', 'service', 'department', 'desk')->whereIn('id', $ids)->get()
                ->each(fn (Ticket $ticket) => $this->requestFor($ticket));
        });
    }

    public function requestFor(Ticket $ticket): ?FeedbackRequest
    {
        $location = $ticket->location;
        $settings = $location->tenant->settings();

        if ($ticket->status !== TicketStatus::Completed || ! $location->feedback_enabled
            || ! app(Features::class)->enabled('feedback')
            || ! $ticket->sms_consent || $ticket->customer_phone === null) {
            return null;
        }
        if (FeedbackRequest::query()->where('ticket_id', $ticket->id)->exists()) {
            return null; // at most once per visit
        }

        $cooldownDays = (int) $settings->get('feedback_cooldown_days', 7);
        if ($ticket->customer_id && $cooldownDays > 0
            && FeedbackRequest::query()->where('customer_id', $ticket->customer_id)->where('created_at', '>=', now()->subDays($cooldownDays))->exists()) {
            return null;
        }

        $request = FeedbackRequest::create([
            'ticket_id' => $ticket->id,
            'customer_id' => $ticket->customer_id,
            'location_id' => $ticket->location_id,
            'token' => Str::random(40),
            'expires_at' => now()->addDays((int) $settings->get('feedback_link_days', 7)),
        ]);

        $this->dispatcher->send(new SmsContext(
            event: NotificationEvent::FeedbackRequest,
            to: $ticket->customer_phone,
            consent: true,
            locationId: $ticket->location_id,
            vars: [
                'first_name' => Str::before(trim($ticket->customer_name), ' '),
                'location' => $location->name,
                'feedback_link' => $request->url(),
            ],
            ticketId: $ticket->id,
            customerId: $ticket->customer_id,
            locale: (string) $settings->get('locale', 'en'),
            sendAt: now()->addMinutes((int) $settings->get('feedback_delay_minutes', 10)),
        ));

        return $request;
    }
}
