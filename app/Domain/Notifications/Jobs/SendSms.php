<?php

namespace App\Domain\Notifications\Jobs;

use App\Domain\Notifications\Models\SmsMessage;
use App\Domain\Notifications\Models\SmsOptOut;
use App\Domain\Notifications\NotificationEvent;
use App\Domain\Notifications\Providers\SmsPermanentException;
use App\Domain\Notifications\Providers\SmsTransientException;
use App\Domain\Notifications\SmsAllowance;
use App\Domain\Notifications\SmsConfig;
use App\Domain\Queue\Models\Ticket;
use App\Domain\Queue\TicketStatus;
use App\Domain\Scheduling\Models\Appointment;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Sends one logged SMS (runs in the dispatching tenant's context). Retries
 * transient provider errors with backoff; drops time-sensitive messages that
 * became stale (sms-notifications "Asynchronous, reliable delivery").
 */
class SendSms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    public function __construct(public readonly int $messageId) {}

    /** @return list<int> seconds between attempts */
    public function backoff(): array
    {
        return [10, 30, 60, 180];
    }

    public function handle(SmsConfig $config, SmsAllowance $allowance): void
    {
        $message = SmsMessage::query()->find($this->messageId);
        if ($message === null || $message->status !== SmsMessage::QUEUED) {
            return;
        }

        if ($reason = $this->staleReason($message)) {
            $message->forceFill(['status' => SmsMessage::SKIPPED, 'status_reason' => $reason])->save();

            return;
        }
        if (SmsOptOut::query()->where('phone', $message->to)->exists()) {
            $message->forceFill(['status' => SmsMessage::SUPPRESSED, 'status_reason' => 'opted out'])->save();

            return;
        }

        $settings = $config->settings($message->location_id);
        $provider = $config->provider($settings);
        $message->forceFill(['attempts' => $message->attempts + 1, 'provider' => $provider->name()])->save();

        try {
            $result = $provider->send($config->sender($settings), $message->to, $message->body, $this->statusCallbackUrl());
        } catch (SmsTransientException $e) {
            if ($this->attempts() >= $this->tries) {
                $message->forceFill(['status' => SmsMessage::FAILED, 'status_reason' => 'retries exhausted: '.$e->getMessage()])->save();

                return;
            }
            throw $e; // retried with backoff
        } catch (SmsPermanentException $e) {
            $message->forceFill(['status' => SmsMessage::FAILED, 'status_reason' => $e->getMessage(), 'error_code' => $e->errorCode])->save();

            return;
        }

        $message->forceFill([
            'status' => $result->status === 'delivered' ? SmsMessage::DELIVERED : SmsMessage::SENT,
            'provider_message_id' => $result->providerMessageId,
            'segments' => $result->segments ?? $message->segments,
            'price' => $result->price,
            'price_unit' => $result->priceUnit,
            'sent_at' => now(),
            'delivered_at' => $result->status === 'delivered' ? now() : null,
        ])->save();

        $allowance->consume($message->segments);
    }

    /** Time-sensitive messages about a ticket that has moved on are dropped. */
    private function staleReason(SmsMessage $message): ?string
    {
        // A reminder (possibly deferred by quiet hours) for an appointment that
        // was cancelled, moved on, or has already started is pointless.
        if ($message->event === NotificationEvent::AppointmentReminder && $message->appointment_id) {
            $appointment = Appointment::query()->find($message->appointment_id);

            return $appointment && $appointment->status->isUpcoming() && $appointment->starts_at->isFuture() ? null : 'stale';
        }

        if (! $message->event->isTimeSensitive() || $message->ticket_id === null) {
            return null;
        }

        $ticket = Ticket::query()->find($message->ticket_id);
        $stillRelevant = $ticket && in_array($ticket->status, [TicketStatus::Waiting, TicketStatus::Called], true);

        return $stillRelevant ? null : 'stale';
    }

    private function statusCallbackUrl(): ?string
    {
        $tenant = app(TenantContext::class)->get();

        return $tenant ? route('public.webhooks.sms.status', $tenant->public_id) : null;
    }
}
