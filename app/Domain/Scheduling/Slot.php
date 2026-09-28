<?php

namespace App\Domain\Scheduling;

use Carbon\CarbonImmutable;

/** A bookable time and the employees free for it. */
final class Slot
{
    /** @param  list<int>  $employeeIds */
    public function __construct(
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
        public readonly array $employeeIds,
    ) {}

    public function key(): string
    {
        return $this->start->utc()->format('Y-m-d\TH:i');
    }
}
