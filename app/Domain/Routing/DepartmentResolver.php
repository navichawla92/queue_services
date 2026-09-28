<?php

namespace App\Domain\Routing;

use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Location;
use App\Domain\Organization\Models\Service;
use App\Domain\Queue\CustomerType;
use App\Domain\Routing\Models\RoutingRule;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Picks a ticket's department (customer-routing "Department routing"): the
 * first matching active rule of the location, else the location's default
 * department for the service. Null when the service is not routable here.
 */
class DepartmentResolver
{
    public function resolve(Location $location, Service $service, CustomerType $type, ?DateTimeInterface $at = null): ?RoutingDecision
    {
        $local = CarbonImmutable::instance($at ?? now())->setTimezone($location->effectiveTimezone());

        $rules = RoutingRule::query()
            ->with('department')
            ->where('location_id', $location->id)
            ->where('is_active', true)
            ->orderBy('sort_order')->orderBy('id')
            ->get();

        foreach ($rules as $rule) {
            if ($rule->department?->is_active && $rule->matches($service, $type, $local)) {
                return new RoutingDecision($rule->department, $rule->priority, $rule);
            }
        }

        $offered = $location->services()->whereKey($service->id)->first();
        if ($offered === null) {
            return null;
        }

        $department = Department::query()->active()->find($offered->getRelation('pivot')->getAttribute('department_id'));

        return $department ? new RoutingDecision($department) : null;
    }
}
