<?php

namespace App\Domain\Notifications;

use App\Domain\Billing\Usage;
use App\Domain\Tenancy\TenantContext;

/**
 * Plan SMS allowance (saas-plans-usage "Limit enforcement"): with policy
 * "block" sending stops at the monthly allowance; with "overage" it
 * continues and usage above the allowance is visible on the usage page.
 */
class SmsAllowance
{
    public function __construct(
        private readonly TenantContext $tenants,
        private readonly Usage $usage,
    ) {}

    public function allows(): bool
    {
        $plan = $this->tenants->get()?->plan;
        $limit = $plan?->limit('sms_monthly');
        if ($limit === null || $plan->option('sms_policy', 'overage') !== 'block') {
            return true;
        }

        return $this->usage->value('sms_segments') < $limit;
    }

    /** Record usage after a successful send. */
    public function consume(int $segments): void
    {
        $this->usage->increment('sms_segments', max(1, $segments));
    }
}
