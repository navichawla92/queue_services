<?php

namespace App\Domain\Billing;

use App\Domain\Tenancy\TenantContext;

/** Plan feature checks for the current tenant (saas-plans-usage "Feature gating"). */
class Features
{
    public function __construct(private readonly TenantContext $tenants) {}

    public function enabled(string $feature): bool
    {
        $plan = $this->tenants->get()?->plan;

        return $plan !== null && $plan->hasFeature($feature);
    }
}
