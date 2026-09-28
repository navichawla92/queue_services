<?php

namespace App\Domain\Organization\Events;

use App\Domain\Organization\EmployeeStatus;
use App\Domain\Organization\Models\Employee;
use Illuminate\Foundation\Events\Dispatchable;

/** Routing / wait estimates / dashboards react to capacity changes. */
class EmployeeStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Employee $employee,
        public readonly EmployeeStatus $from,
        public readonly EmployeeStatus $to,
    ) {}
}
