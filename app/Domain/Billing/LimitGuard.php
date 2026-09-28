<?php

namespace App\Domain\Billing;

use App\Domain\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

/**
 * Refuses creating resources beyond the plan (saas-plans-usage "Limit
 * enforcement"): locations, staff users, displays.
 */
class LimitGuard
{
    public function __construct(
        private readonly TenantContext $tenants,
        private readonly Usage $usage,
    ) {}

    /** @throws ValidationException */
    public function assertCanAdd(string $resource, string $field = 'limit'): void
    {
        $limit = $this->tenants->require()->plan?->limit($resource);
        if ($limit === null) {
            return;
        }

        $used = $this->usage->resources()[$resource] ?? 0;
        if ($used >= $limit) {
            throw ValidationException::withMessages([$field => __('Your plan allows :limit :what. Upgrade your plan to add more.', [
                'limit' => $limit, 'what' => mb_strtolower(PlanCatalog::LIMITS[$resource] ?? $resource),
            ])]);
        }
    }
}
