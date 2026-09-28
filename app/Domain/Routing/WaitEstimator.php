<?php

namespace App\Domain\Routing;

/**
 * Wait estimate (customer-routing "Estimated wait time", design Decision 7):
 *   ceil(ahead / max(1, staff)) × avgServiceMinutes, shown as a 5-minute range.
 * With no eligible staff working the estimate is unavailable (null).
 */
class WaitEstimator
{
    public const RANGE_MINUTES = 5;

    public function minutes(int $ahead, int $workingStaff, float $avgServiceMinutes): ?int
    {
        if ($workingStaff < 1) {
            return null;
        }

        return (int) round(ceil($ahead / $workingStaff) * $avgServiceMinutes);
    }

    /** @return array{0: int, 1: int}|null lower and upper bound in minutes */
    public function range(?int $minutes): ?array
    {
        if ($minutes === null) {
            return null;
        }

        $low = intdiv($minutes, self::RANGE_MINUTES) * self::RANGE_MINUTES;

        return [$low, $low + self::RANGE_MINUTES];
    }

    public function label(?int $minutes): string
    {
        $range = $this->range($minutes);

        return match (true) {
            $range === null => __('Wait time unavailable'),
            $range[1] <= self::RANGE_MINUTES => __('Less than :max min', ['max' => $range[1]]),
            default => __('About :low–:high min', ['low' => $range[0], 'high' => $range[1]]),
        };
    }
}
