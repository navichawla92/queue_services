<?php

namespace App\Domain\Routing;

use App\Domain\Organization\Models\Location;
use App\Domain\Organization\Models\Service;
use App\Domain\Queue\CustomerType;
use DateTimeInterface;

/**
 * Self-service surfaces may only issue a ticket when the service routes to a
 * department that has at least one configured, eligible employee
 * (customer-routing "Unroutable service"). Receptionists may override.
 */
class Routability
{
    public function __construct(
        private readonly DepartmentResolver $resolver,
        private readonly EligibleEmployees $eligible,
    ) {}

    public function check(Location $location, Service $service, CustomerType $type, ?DateTimeInterface $at = null): ?RoutingDecision
    {
        $decision = $this->resolver->resolve($location, $service, $type, $at);

        if ($decision === null) {
            return null;
        }

        return $this->eligible->configured($location, $decision->department->id, $service->id)->exists()
            ? $decision
            : null;
    }
}
