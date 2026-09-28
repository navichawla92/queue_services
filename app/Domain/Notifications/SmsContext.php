<?php

namespace App\Domain\Notifications;

/** What to send, to whom, about what (input to NotificationDispatcher). */
final class SmsContext
{
    /** @param  array<string, string|int|null>  $vars  template placeholder values */
    public function __construct(
        public readonly NotificationEvent $event,
        public readonly ?string $to,
        public readonly bool $consent,
        public readonly ?int $locationId,
        public readonly array $vars,
        public readonly ?int $ticketId = null,
        public readonly ?int $appointmentId = null,
        public readonly ?int $customerId = null,
        public readonly string $locale = 'en',
        public readonly ?\DateTimeInterface $sendAt = null,
    ) {}
}
