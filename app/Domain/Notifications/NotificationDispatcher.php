<?php

namespace App\Domain\Notifications;

use App\Domain\Notifications\Jobs\SendSms;
use App\Domain\Notifications\Models\SmsMessage;
use App\Domain\Notifications\Models\SmsOptOut;
use App\Domain\Organization\Models\Location;
use App\Domain\Tenancy\TenantContext;
use Carbon\CarbonImmutable;

/**
 * Decides whether and when an SMS goes out, then logs it and queues the send
 * (design Decision 8). Checks, in order: tenant active → event enabled →
 * phone + consent → opt-out → allowance → quiet hours (non-urgent only).
 * Every decision is visible in the message log.
 */
class NotificationDispatcher
{
    public function __construct(
        private readonly TenantContext $tenants,
        private readonly NotificationPreferences $preferences,
        private readonly TemplateRenderer $renderer,
        private readonly SmsConfig $config,
        private readonly SmsAllowance $allowance,
    ) {}

    public function send(SmsContext $ctx): ?SmsMessage
    {
        $tenant = $this->tenants->get();
        if ($tenant === null || ! $tenant->isActive()) {
            return null;
        }
        if (! $this->preferences->enabled($ctx->event, $ctx->locationId)) {
            return null;
        }
        if ($ctx->to === null || ! $ctx->consent) {
            return null; // no consent for this visit: nothing is sent or logged
        }

        $body = $this->renderer->render($this->preferences->template($ctx->event, $ctx->locale), $ctx->vars);
        $message = new SmsMessage([
            'location_id' => $ctx->locationId,
            'ticket_id' => $ctx->ticketId,
            'appointment_id' => $ctx->appointmentId,
            'customer_id' => $ctx->customerId,
            'to' => $ctx->to,
            'event' => $ctx->event,
            'body' => $body,
            'segments' => $this->renderer->segments($body),
            'status' => SmsMessage::QUEUED,
        ]);

        if (SmsOptOut::query()->where('phone', $ctx->to)->exists()) {
            return $this->finish($message, SmsMessage::SUPPRESSED, 'opted out');
        }
        if (! $this->allowance->allows()) {
            return $this->finish($message, SmsMessage::SUPPRESSED, 'plan SMS allowance used up');
        }

        $sendAt = $this->sendTime($ctx);
        $message->forceFill(['scheduled_for' => $sendAt]);
        $message->save();

        $job = new SendSms($message->id);
        $sendAt !== null && $sendAt->isFuture()
            ? dispatch($job)->delay($sendAt)
            : dispatch($job);

        return $message;
    }

    private function finish(SmsMessage $message, string $status, string $reason): SmsMessage
    {
        $message->status = $status;
        $message->status_reason = $reason;
        $message->save();

        return $message;
    }

    /** Requested time, pushed out of quiet hours for non-urgent events. */
    private function sendTime(SmsContext $ctx): ?CarbonImmutable
    {
        $at = $ctx->sendAt ? CarbonImmutable::instance($ctx->sendAt) : null;
        if (! $ctx->event->respectsQuietHours()) {
            return $at;
        }

        $location = $ctx->locationId ? Location::query()->find($ctx->locationId) : null;
        $tz = $location?->effectiveTimezone() ?? $this->tenants->require()->settings()->timezone();
        $settings = $this->config->settings($ctx->locationId);

        $local = ($at ?? CarbonImmutable::now())->setTimezone($tz);
        [$qsH, $qsM] = array_map('intval', explode(':', $settings->quiet_start));
        [$qeH, $qeM] = array_map('intval', explode(':', $settings->quiet_end));
        $minutes = $local->hour * 60 + $local->minute;
        $start = $qsH * 60 + $qsM;
        $end = $qeH * 60 + $qeM;

        $inQuiet = $start > $end
            ? ($minutes >= $start || $minutes < $end)   // e.g. 21:00–08:00 (overnight)
            : ($minutes >= $start && $minutes < $end);

        if (! $inQuiet) {
            return $at;
        }

        $resume = $local->setTime($qeH, $qeM);
        if ($resume->lessThanOrEqualTo($local)) {
            $resume = $resume->addDay();
        }

        return $resume->setTimezone('UTC');
    }
}
