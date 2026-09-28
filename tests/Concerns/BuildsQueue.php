<?php

namespace Tests\Concerns;

use App\Domain\Organization\EmployeeShift;
use App\Domain\Organization\EmployeeStatus;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Desk;
use App\Domain\Organization\Models\Employee;
use App\Domain\Organization\Models\Location;
use App\Domain\Organization\Models\OpeningHour;
use App\Domain\Organization\Models\Service;
use App\Domain\Queue\Actor;
use App\Domain\Queue\CheckinChannel;
use App\Domain\Queue\CheckinRequest;
use App\Domain\Queue\CustomerType;
use App\Domain\Queue\IssueTicket;
use App\Domain\Queue\Models\Ticket;
use App\Domain\Tenancy\Models\Tenant;

/**
 * Builds a ready-to-serve queue in the current tenant: a location open all
 * week (UTC), department "A", a service offered there, and helpers to add
 * on-shift employees and issue tickets.
 */
trait BuildsQueue
{
    protected Location $location;

    protected Department $dept;

    protected Service $service;

    protected function buildQueue(Tenant $tenant, string $timezone = 'UTC'): void
    {
        $tenant->forceFill(['settings' => array_merge($tenant->settings ?? [], ['timezone' => $timezone])])->save();
        $this->actingAsTenant($tenant->fresh());

        $this->location = Location::factory()->create(['name' => 'Main', 'walkin_cutoff_minutes' => 0]);
        foreach (range(0, 6) as $day) {
            OpeningHour::create(['location_id' => $this->location->id, 'weekday' => $day, 'opens_at' => '00:00', 'closes_at' => '23:59']);
        }
        $this->dept = Department::factory()->create(['location_id' => $this->location->id, 'prefix' => 'A', 'name' => 'General']);
        $this->service = Service::factory()->create(['name' => 'General help', 'expected_minutes' => 8]);
        $this->service->offerAt($this->location, $this->dept);
    }

    /** An employee in the department, skilled in the given services, on shift at a desk. */
    protected function onShiftEmployee(string $name = 'Maria', array $services = [], ?Department $dept = null, bool $start = true): Employee
    {
        $employee = Employee::factory()->create(['display_name' => $name]);
        $employee->user->locations()->attach($this->location);
        $employee->departments()->attach(($dept ?? $this->dept)->id);
        $employee->services()->attach(collect($services ?: [$this->service])->pluck('id'));

        if ($start) {
            $desk = Desk::factory()->create(['location_id' => $this->location->id, 'label' => 'Desk '.$employee->id]);
            app(EmployeeShift::class)->setStatus($employee, EmployeeStatus::Available, $this->location, $desk);
        }

        return $employee->fresh();
    }

    protected function issue(string $name = 'John Doe', ?string $phone = null, array $overrides = []): Ticket
    {
        $request = new CheckinRequest(
            location: $overrides['location'] ?? $this->location,
            service: $overrides['service'] ?? $this->service,
            name: $name,
            phone: $phone,
            smsConsent: $overrides['smsConsent'] ?? ($phone !== null),
            channel: $overrides['channel'] ?? CheckinChannel::Receptionist,
            customerType: $overrides['customerType'] ?? CustomerType::WalkIn,
            department: $overrides['department'] ?? null,
            extraPriority: $overrides['priority'] ?? 0,
            assignedEmployeeId: $overrides['assignedEmployeeId'] ?? null,
        );

        return app(IssueTicket::class)($request, Actor::system());
    }
}
