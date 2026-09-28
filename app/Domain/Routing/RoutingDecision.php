<?php

namespace App\Domain\Routing;

use App\Domain\Organization\Models\Department;
use App\Domain\Routing\Models\RoutingRule;

final class RoutingDecision
{
    public function __construct(
        public readonly Department $department,
        public readonly int $priority = 0,
        public readonly ?RoutingRule $rule = null,
    ) {}
}
