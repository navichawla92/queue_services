<?php

namespace App\Domain\Notifications\Providers;

/**
 * Swappable SMS provider (design Decision 8). Implementations throw
 * SmsTransientException for retryable failures (5xx, 429, timeouts) and
 * SmsPermanentException for the rest (bad number, auth, blocked).
 */
interface SmsProvider
{
    public function name(): string;

    public function send(SmsSender $sender, string $to, string $body, ?string $statusCallbackUrl = null): SmsResult;
}
