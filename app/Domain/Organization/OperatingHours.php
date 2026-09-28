<?php

namespace App\Domain\Organization;

use App\Domain\Organization\Models\Closure;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Location;
use App\Domain\Organization\Models\OpeningHour;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Opening windows, walk-in acceptance and next opening, evaluated in the
 * location's local time (organization-setup "Operating hours and closures").
 *
 *  - Location hours: weekly rows with department_id null.
 *  - Department hours: if a department has any weekly rows, it uses only
 *    those (intersected with the location's hours); otherwise it inherits.
 *  - Closures on a date replace the weekly hours (closed, or special hours).
 *  - Walk-ins are refused in the last `walkin_cutoff_minutes` of a window.
 */
class OperatingHours
{
    /** Days searched for the next opening. */
    private const LOOKAHEAD_DAYS = 14;

    /**
     * Opening windows on a local calendar date.
     *
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    public function windows(Location $location, DateTimeInterface $date, ?Department $department = null): array
    {
        $tz = $location->effectiveTimezone();
        $day = CarbonImmutable::parse($date->format('Y-m-d'), $tz)->startOfDay();

        $locationWindows = $this->closureWindows($location, null, $day)
            ?? $this->weeklyWindows($location, null, $day);

        if ($department === null) {
            return $locationWindows;
        }

        $departmentWindows = $this->closureWindows($location, $department, $day)
            ?? ($this->hasOwnHours($department)
                ? $this->weeklyWindows($location, $department, $day)
                : $locationWindows);

        return $this->intersect($locationWindows, $departmentWindows);
    }

    public function isOpen(Location $location, DateTimeInterface $at, ?Department $department = null): bool
    {
        return $this->currentWindow($location, $at, $department, 0) !== null;
    }

    /** Open and not within the last-walk-in cutoff. */
    public function acceptsWalkIns(Location $location, DateTimeInterface $at, ?Department $department = null): bool
    {
        if (! $location->is_active) {
            return false;
        }

        return $this->currentWindow($location, $at, $department, $location->walkin_cutoff_minutes) !== null;
    }

    /** Start of the next window after $from (location local time), or null if none soon. */
    public function nextOpening(Location $location, DateTimeInterface $from, ?Department $department = null): ?CarbonImmutable
    {
        $local = CarbonImmutable::instance($from)->setTimezone($location->effectiveTimezone());

        for ($d = 0; $d <= self::LOOKAHEAD_DAYS; $d++) {
            foreach ($this->windows($location, $local->addDays($d), $department) as [$start]) {
                if ($start->greaterThan($local)) {
                    return $start;
                }
            }
        }

        return null;
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable}|null */
    private function currentWindow(Location $location, DateTimeInterface $at, ?Department $department, int $cutoffMinutes): ?array
    {
        $local = CarbonImmutable::instance($at)->setTimezone($location->effectiveTimezone());

        foreach ($this->windows($location, $local, $department) as $window) {
            [$start, $end] = $window;
            if ($local->greaterThanOrEqualTo($start) && $local->lessThan($end->subMinutes($cutoffMinutes))) {
                return $window;
            }
        }

        return null;
    }

    /** @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>|null null = no closure that day */
    private function closureWindows(Location $location, ?Department $department, CarbonImmutable $day): ?array
    {
        $closure = Closure::query()
            ->where('location_id', $location->id)
            ->where('department_id', $department?->id)
            ->whereDate('date', $day->toDateString())
            ->first();

        if ($closure === null) {
            return null;
        }

        return $closure->isClosedAllDay()
            ? []
            : [[$this->at($day, $closure->opens_at), $this->at($day, $closure->closes_at)]];
    }

    /** @return list<array{0: CarbonImmutable, 1: CarbonImmutable}> */
    private function weeklyWindows(Location $location, ?Department $department, CarbonImmutable $day): array
    {
        return OpeningHour::query()
            ->where('location_id', $location->id)
            ->where('department_id', $department?->id)
            ->where('weekday', $day->dayOfWeek)
            ->orderBy('opens_at')
            ->get()
            ->map(fn (OpeningHour $h) => [$this->at($day, $h->opens_at), $this->at($day, $h->closes_at)])
            ->values()->all();
    }

    private function hasOwnHours(Department $department): bool
    {
        return OpeningHour::query()->where('department_id', $department->id)->exists();
    }

    private function at(CarbonImmutable $day, string $time): CarbonImmutable
    {
        [$h, $m] = array_map('intval', explode(':', $time));

        return $day->setTime($h, $m);
    }

    /**
     * @param  list<array{0: CarbonImmutable, 1: CarbonImmutable}>  $a
     * @param  list<array{0: CarbonImmutable, 1: CarbonImmutable}>  $b
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function intersect(array $a, array $b): array
    {
        $result = [];
        foreach ($a as [$aStart, $aEnd]) {
            foreach ($b as [$bStart, $bEnd]) {
                $start = $aStart->max($bStart);
                $end = $aEnd->min($bEnd);
                if ($start->lessThan($end)) {
                    $result[] = [$start, $end];
                }
            }
        }

        usort($result, fn ($x, $y) => $x[0] <=> $y[0]);

        return $result;
    }
}
