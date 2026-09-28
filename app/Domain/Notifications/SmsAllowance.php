<?php

namespace App\Domain\Notifications;

/**
 * Plan SMS allowance gate (saas-plans-usage "Limit enforcement"). Returns
 * true when sending is allowed; the plan-aware implementation is bound by
 * the billing domain.
 */
class SmsAllowance
{
    public function allows(): bool
    {
        return true;
    }

    /** Record usage after a successful send. */
    public function consume(int $segments): void {}
}
