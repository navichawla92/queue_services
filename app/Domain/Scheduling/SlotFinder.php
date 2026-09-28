<?php

namespace App\Domain\Scheduling;

use App\Domain\Organization\Models\Employee;
use App\Domain\Organization\Models\Location;
use App\Domain\Organization\Models\Service;
use App\Domain\Organization\OperatingHours;
use App\Domain\Queue\CustomerType;
use App\Domain\Routing\DepartmentResolver;
use App\Domain\Routing\EligibleEmployees;
use App\Domain\Scheduling\Models\Appointment;
use App\Domain\Scheduling\Models\EmployeeSchedule;
use App\Domain\Scheduling\Models\TimeOff;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Bookable slots (appointment-scheduling "Employee availability", "Location
 * capacity", "Booking window"; design Decision 9): location/department hours
 * ∩ employee schedule − time off − existing appointments − capacity caps,
 * within [now + lead, today + horizon]. Slots are computed, never stored.
 */
class SlotFinder
{
    public const GRID_MINUTES = 15;

    public function __construct(
        private readonly OperatingHours $hours,
        private readonly DepartmentResolver $resolver,
        private readonly EligibleEmployees $eligible,
    ) {}

    public function durationMinutes(Location $location, Service $service): int
    {
        return $service->expected_minutes + $location->booking_buffer_minutes;
    }

    /**
     * @param  DateTimeInterface  $date  local calendar date at the location
     * @param  bool  $enforceWindow  false for staff bookings (lead/horizon ignored)
     * @return list<Slot>
     */
    public function slots(Location $location, Service $service, DateTimeInterface $date, ?Employee $employee = null, bool $enforceWindow = true, ?int $ignoreAppointmentId = null): array
    {
        $tz = $location->effectiveTimezone();
        $day = CarbonImmutable::parse($date->format('Y-m-d'), $tz)->startOfDay();
        $now = CarbonImmutable::now();

        if ($enforceWindow && $day->greaterThan($now->setTimezone($tz)->startOfDay()->addDays($location->booking_horizon_days))) {
            return [];
        }

        $decision = $this->resolver->resolve($location, $service, CustomerType::Appointment, $day->setTime(12, 0));
        if ($decision === null || ! $service->allow_appointment) {
            return [];
        }

        $openWindows = $this->hours->windows($location, $day, $decision->department);
        if ($openWindows === []) {
            return [];
        }

        $employees = $this->eligible->configured($location, $decision->department->id, $service->id)
            ->when($employee, fn ($q) => $q->whereKey($employee->id))
            ->get();
        if ($employees->isEmpty()) {
            return [];
        }

        $duration = $this->durationMinutes($location, $service);
        $earliest = $enforceWindow ? $now->addMinutes($location->booking_lead_minutes) : $now;
        $dayEndUtc = $day->endOfDay()->utc();

        // Busy intervals per employee (appointments + time off) for the day.
        $busy = [];
        Appointment::query()->occupying()
            ->whereIn('employee_id', $employees->pluck('id'))
            ->where('starts_at', '<', $dayEndUtc)->where('ends_at', '>', $day->utc())
            ->when($ignoreAppointmentId, fn ($q) => $q->where('id', '!=', $ignoreAppointmentId))
            ->get(['employee_id', 'starts_at', 'ends_at'])
            ->each(function (Appointment $a) use (&$busy) {
                $busy[$a->employee_id][] = [CarbonImmutable::instance($a->starts_at), CarbonImmutable::instance($a->ends_at)];
            });
        TimeOff::query()->whereIn('employee_id', $employees->pluck('id'))
            ->where('starts_at', '<', $dayEndUtc)->where('ends_at', '>', $day->utc())
            ->get()->each(function (TimeOff $t) use (&$busy) {
                $busy[$t->employee_id][] = [CarbonImmutable::instance($t->starts_at), CarbonImmutable::instance($t->ends_at)];
            });

        $schedules = EmployeeSchedule::query()
            ->whereIn('employee_id', $employees->pluck('id'))
            ->where('location_id', $location->id)
            ->where('weekday', $day->dayOfWeek)
            ->get()->groupBy('employee_id');

        $capacityCounts = $this->capacityCounts($location, $day, $ignoreAppointmentId);

        /** @var array<string, array{start: CarbonImmutable, end: CarbonImmutable, employees: list<int>}> $found */
        $found = [];
        foreach ($employees as $emp) {
            foreach ($schedules->get($emp->id, collect()) as $shift) {
                $shiftStart = $this->at($day, $shift->starts_at);
                $shiftEnd = $this->at($day, $shift->ends_at);

                foreach ($openWindows as [$openStart, $openEnd]) {
                    $from = $shiftStart->max($openStart);
                    $to = $shiftEnd->min($openEnd);

                    for ($t = $this->alignToGrid($from); $t->addMinutes($duration)->lessThanOrEqualTo($to); $t = $t->addMinutes(self::GRID_MINUTES)) {
                        $end = $t->addMinutes($duration);
                        if ($t->lessThan($earliest) || $this->overlapsAny($t, $end, $busy[$emp->id] ?? [])) {
                            continue;
                        }
                        $key = $t->utc()->format('Y-m-d\TH:i');
                        $found[$key] ??= ['start' => $t, 'end' => $end, 'employees' => []];
                        $found[$key]['employees'][] = $emp->id;
                    }
                }
            }
        }

        ksort($found);
        $slots = [];
        foreach ($found as $slot) {
            if ($location->appointment_capacity_per_hour !== null
                && ($capacityCounts[$slot['start']->format('H')] ?? 0) >= $location->appointment_capacity_per_hour) {
                continue;
            }
            $slots[] = new Slot($slot['start'], $slot['end'], array_values(array_unique($slot['employees'])));
        }

        return $slots;
    }

    /** Is this exact start time bookable (optionally for this employee)? */
    public function find(Location $location, Service $service, DateTimeInterface $start, ?Employee $employee = null, bool $enforceWindow = true, ?int $ignoreAppointmentId = null): ?Slot
    {
        $local = CarbonImmutable::instance($start)->setTimezone($location->effectiveTimezone());

        foreach ($this->slots($location, $service, $local, $employee, $enforceWindow, $ignoreAppointmentId) as $slot) {
            if ($slot->start->equalTo($local)) {
                return $slot;
            }
        }

        return null;
    }

    /** @return array<string, int> appointments per local hour ("09" => 2) on the day */
    private function capacityCounts(Location $location, CarbonImmutable $day, ?int $ignoreId): array
    {
        if ($location->appointment_capacity_per_hour === null) {
            return [];
        }

        $counts = [];
        Appointment::query()->occupying()->where('location_id', $location->id)
            ->whereBetween('starts_at', [$day->utc(), $day->endOfDay()->utc()])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->pluck('starts_at')
            ->each(function ($start) use (&$counts, $day) {
                $hour = CarbonImmutable::instance($start)->setTimezone($day->getTimezone())->format('H');
                $counts[$hour] = ($counts[$hour] ?? 0) + 1;
            });

        return $counts;
    }

    /** @param  list<array{0: CarbonImmutable, 1: CarbonImmutable}>  $intervals */
    private function overlapsAny(CarbonImmutable $start, CarbonImmutable $end, array $intervals): bool
    {
        foreach ($intervals as [$s, $e]) {
            if ($start->lessThan($e) && $end->greaterThan($s)) {
                return true;
            }
        }

        return false;
    }

    private function alignToGrid(CarbonImmutable $t): CarbonImmutable
    {
        $minutes = $t->hour * 60 + $t->minute;
        $aligned = (int) ceil($minutes / self::GRID_MINUTES) * self::GRID_MINUTES;

        return $t->startOfDay()->addMinutes($aligned);
    }

    private function at(CarbonImmutable $day, string $time): CarbonImmutable
    {
        [$h, $m] = array_map('intval', explode(':', $time));

        return $day->setTime($h, $m);
    }
}
