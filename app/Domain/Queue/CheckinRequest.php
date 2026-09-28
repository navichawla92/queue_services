<?php

namespace App\Domain\Queue;

use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Location;
use App\Domain\Organization\Models\Service;

/** Input for issuing a ticket (walk-in or arriving appointment). */
final class CheckinRequest
{
    public function __construct(
        public readonly Location $location,
        public readonly Service $service,
        public readonly string $name,
        public readonly ?string $phone,          // E.164 or null
        public readonly bool $smsConsent,
        public readonly CheckinChannel $channel,
        public readonly CustomerType $customerType = CustomerType::WalkIn,
        public readonly ?Department $department = null, // explicit choice (customer or receptionist)
        public readonly int $extraPriority = 0,
        public readonly ?int $appointmentId = null,
        public readonly ?int $assignedEmployeeId = null,
    ) {}
}
