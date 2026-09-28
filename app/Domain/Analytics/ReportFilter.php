<?php

namespace App\Domain\Analytics;

use App\Domain\Access\LocationAccess;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Report filters (analytics-reporting "Report filters"). Dates are local
 * calendar dates, applied per location via each ticket's local_date.
 * Location ids are always intersected with the user's access scope.
 */
final class ReportFilter
{
    /** @param  list<int>  $locationIds  already scope-checked; empty = no access */
    public function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly array $locationIds,
        public readonly ?int $departmentId = null,
        public readonly ?int $serviceId = null,
        public readonly ?int $employeeId = null,
    ) {}

    public static function for(User $user, string $from, string $to, ?int $locationId = null, ?int $departmentId = null, ?int $serviceId = null, ?int $employeeId = null): self
    {
        $allowed = app(LocationAccess::class)->accessibleLocations($user)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $locations = $locationId ? array_values(array_intersect($allowed, [$locationId])) : $allowed;

        $f = CarbonImmutable::parse($from)->startOfDay();
        $t = CarbonImmutable::parse($to)->startOfDay();

        return new self($f->min($t), $f->max($t), $locations, $departmentId, $serviceId, $employeeId);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'from' => $this->from->toDateString(), 'to' => $this->to->toDateString(),
            'locations' => $this->locationIds, 'department' => $this->departmentId,
            'service' => $this->serviceId, 'employee' => $this->employeeId,
        ];
    }
}
