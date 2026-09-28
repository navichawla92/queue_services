<?php

namespace App\Domain\Routing;

use App\Domain\Organization\EmployeeStatus;
use App\Domain\Organization\Models\Employee;
use App\Domain\Organization\Models\Location;
use Illuminate\Database\Eloquent\Builder;

/**
 * Employees who may serve a ticket (customer-routing "Eligible employees"):
 * active account, works at the location, belongs to the department and is
 * skilled in the service.
 */
class EligibleEmployees
{
    /** @return Builder<Employee> configured to serve, regardless of live status */
    public function configured(Location $location, int $departmentId, int $serviceId): Builder
    {
        return Employee::query()
            ->whereHas('user', fn ($q) => $q->where('is_active', true)
                ->where(fn ($q) => $q->where('all_locations', true)
                    ->orWhereHas('locations', fn ($l) => $l->whereKey($location->id))))
            ->whereHas('departments', fn ($q) => $q->whereKey($departmentId))
            ->whereHas('services', fn ($q) => $q->whereKey($serviceId));
    }

    /** @return Builder<Employee> currently working here (available or busy): capacity for estimates */
    public function working(Location $location, int $departmentId, int $serviceId): Builder
    {
        return $this->configured($location, $departmentId, $serviceId)
            ->where('current_location_id', $location->id)
            ->whereIn('status', [EmployeeStatus::Available->value, EmployeeStatus::Busy->value]);
    }

    /** Can this employee serve the ticket's department & service at this location? */
    public function isEligible(Employee $employee, Location $location, int $departmentId, int $serviceId): bool
    {
        return $this->configured($location, $departmentId, $serviceId)->whereKey($employee->id)->exists();
    }
}
