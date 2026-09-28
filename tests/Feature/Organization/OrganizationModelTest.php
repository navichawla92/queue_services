<?php

namespace Tests\Feature\Organization;

use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Employee;
use App\Domain\Organization\Models\Location;
use App\Domain\Organization\Models\Service;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class OrganizationModelTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_department_prefix_is_uppercased_and_unique_per_location(): void
    {
        [$a] = $this->twoTenants();
        $this->actingAsTenant($a);
        [$x, $y] = Location::factory()->count(2)->create();

        $dept = Department::factory()->create(['location_id' => $x->id, 'prefix' => 'a']);
        $this->assertSame('A', $dept->prefix);

        // Same prefix at another location is fine.
        Department::factory()->create(['location_id' => $y->id, 'prefix' => 'A']);

        $this->expectException(UniqueConstraintViolationException::class);
        Department::factory()->create(['location_id' => $x->id, 'prefix' => 'A']);
    }

    public function test_service_is_offered_per_location_with_that_locations_default_department(): void
    {
        [$a] = $this->twoTenants();
        $this->actingAsTenant($a);
        [$x, $y] = Location::factory()->count(2)->create();
        $xLoans = Department::factory()->create(['location_id' => $x->id, 'prefix' => 'L']);
        $yGeneral = Department::factory()->create(['location_id' => $y->id, 'prefix' => 'G']);
        $loans = Service::factory()->create(['name' => 'Loans']);
        $hidden = Service::factory()->create(['name' => 'Internal', 'customer_selectable' => false]);

        $loans->offerAt($x, $xLoans);
        $loans->offerAt($y, $yGeneral);
        $hidden->offerAt($x, $xLoans);

        $this->assertSame($xLoans->id, $x->services()->find($loans->id)->pivot->department_id);
        $this->assertSame($yGeneral->id, $y->services()->find($loans->id)->pivot->department_id);
        $this->assertSame(['Internal', 'Loans'], Service::query()->offeredAt($x)->pluck('name')->sort()->values()->all());
        $this->assertSame(['Loans'], Service::query()->offeredAt($x, customerFacing: true)->pluck('name')->all());
    }

    public function test_service_cannot_default_to_another_locations_department(): void
    {
        [$a] = $this->twoTenants();
        $this->actingAsTenant($a);
        [$x, $y] = Location::factory()->count(2)->create();
        $yDept = Department::factory()->create(['location_id' => $y->id]);

        $this->expectException(HttpException::class);
        Service::factory()->create()->offerAt($x, $yDept);
    }

    public function test_employee_skills_departments_and_status_changes_are_not_audited_as_setup(): void
    {
        [$a] = $this->twoTenants();
        $this->actingAsTenant($a);
        $employee = Employee::factory()->create(['display_name' => 'Maria']);
        $service = Service::factory()->create();
        $employee->services()->attach($service);

        $this->assertTrue($employee->services()->whereKey($service->id)->exists());
        $this->assertSame($a->id, $employee->user->tenant_id);

        $employee->forceFill(['status' => 'available', 'status_changed_at' => now()])->save();
        $this->assertDatabaseMissing('audit_logs', ['action' => 'employee.updated']);
    }
}
