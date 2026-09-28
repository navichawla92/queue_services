<?php

namespace App\Domain\Notifications\Providers;

final class SmsResult
{
    public function __construct(
        public readonly string $providerMessageId,
        public readonly string $status,     // queued | sent | delivered
        public readonly ?int $segments = null,
        public readonly ?string $price = null,
        public readonly ?string $priceUnit = null,
    ) {}
}
