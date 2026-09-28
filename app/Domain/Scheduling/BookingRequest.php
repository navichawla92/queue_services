<?php

namespace App\Domain\Scheduling;

use App\Domain\Organization\Models\Employee;
use App\Domain\Organization\Models\Location;
use App\Domain\Organization\Models\Service;
use DateTimeInterface;

final class BookingRequest
{
    public function __construct(
        public readonly Location $location,
        public readonly Service $service,
        public readonly DateTimeInterface $start,
        public readonly string $name,
        public readonly ?string $phone,       // E.164
        public readonly ?string $email,
        public readonly bool $smsConsent,
        public readonly ?Employee $employee = null,
        public readonly string $source = 'online',   // online | staff
        public readonly bool $override = false,      // staff: book outside availability
        public readonly ?string $notes = null,
    ) {}
}
