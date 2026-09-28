<?php

namespace App\Domain\Notifications\Providers;

final class SmsSender
{
    public function __construct(
        public readonly ?string $fromNumber,
        public readonly ?string $messagingServiceSid = null,
    ) {}
}
