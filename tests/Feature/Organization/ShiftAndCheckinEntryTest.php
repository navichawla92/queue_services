<?php

namespace Tests\Feature\Organization;

use App\Domain\Access\CurrentLocation;
use App\Domain\Access\Roles;
use App\Domain\Organization\EmployeeShift;
use App\Domain\Organization\EmployeeStatus;
use App\Domain\Organization\Events\EmployeeStatusChanged;
use App\Domain\Organization\Models\Desk;
use App\Domain\Organization\Models\Employee;
use App\Domain\Organization\Models\Location;
use App\Domain\Organization\Models\OpeningHour;
use App\Livewire\Staff\MyStatus;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ShiftAndCheckinEntryTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_starting_shift_uses_default_desk_and_desk_change_is_announced_location(): void
    {
        Event::fake([EmployeeStatusChanged::class]);
        [$a] = $this->twoTenants();
        $this->actingAsTenant($a);
        $location = Location::factory()->create();
        [$desk3, $desk5] = Desk::factory()->count(2)->sequence(['label' => 'Desk 3'], ['label' => 'Desk 5'])->create(['location_id' => $location->id]);
        $employee = Employee::factory()->create(['default_desk_id' => $desk3->id]);
        $employee->user->locations()->attach($location);
        $shift = app(EmployeeShift::class);

        $shift->setStatus($employee, EmployeeStatus::Available, $location);
        $this->assertSame($desk3->id, $employee->current_desk_id);
        $this->assertTrue($employee->status->acceptsNewTickets());
        Event::assertDispatched(EmployeeStatusChanged::class);

        $shift->changeDesk($employee, $desk5);
        $this->assertSame('Desk 5', $employee->fresh()->currentDesk->label);

        $shift->setStatus($employee, EmployeeStatus::OnBreak, $location);
        $this->assertFalse($employee->status->acceptsNewTickets());
        $this->assertSame($desk5->id, $employee->current_desk_id, 'break keeps desk');

        $shift->setStatus($employee, EmployeeStatus::Offline, $location);
        $this->assertNull($employee->current_desk_id);
        $this->assertNull($employee->current_location_id);
    }

    public function test_cannot_work_at_unassigned_location_or_use_foreign_desk(): void
    {
        [$a] = $this->twoTenants();
        $this->actingAsTenant($a);
        [$x, $y] = Location::factory()->count(2)->create();
        $yDesk = Desk::factory()->create(['location_id' => $y->id]);
        $employee = Employee::factory()->create();
        $employee->user->locations()->attach($x);
        $shift = app(EmployeeShift::class);

        try {
            $shift->setStatus($employee, EmployeeStatus::Available, $y);
            $this->fail('Expected validation error');
        } catch (ValidationException) {
        }

        $this->expectException(ValidationException::class);
        $shift->setStatus($employee, EmployeeStatus::Available, $x, $yDesk);
    }

    public function test_my_status_component_starts_shift_at_current_location(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        [$a] = $this->twoTenants();
        $this->actingAsTenant($a);
        $location = Location::factory()->create();
        $desk = Desk::factory()->create(['location_id' => $location->id, 'label' => 'Room B']);
        $employee = Employee::factory()->create();
        $employee->user->assignRole(Roles::EMPLOYEE);
        $employee->user->locations()->attach($location);

        $this->actingAs($employee->user)->get('/staff')->assertOk()->assertSee('my-status', false);

        app(CurrentLocation::class)->set($location);
        Livewire::test(MyStatus::class)
            ->set('deskId', $desk->id)
            ->call('setStatus', 'available')
            ->assertSee('Available');

        $this->assertSame($desk->id, $employee->fresh()->current_desk_id);
        Livewire::test(MyStatus::class)->call('setStatus', 'busy')->assertStatus(422);
    }

    public function test_public_checkin_entry_reports_status_and_resolves_by_public_id(): void
    {
        [$a] = $this->twoTenants();
        $a->forceFill(['settings' => ['timezone' => 'UTC']])->save();
        $this->actingAsTenant($a->fresh());
        $location = Location::factory()->create(['name' => 'Main Street', 'walkin_cutoff_minutes' => 0]);
        OpeningHour::create(['location_id' => $location->id, 'weekday' => now('UTC')->dayOfWeek, 'opens_at' => '00:00', 'closes_at' => '23:59']);
        $this->tenantContext()->clear();

        $this->get('/c/'.$location->checkin_public_id)->assertOk()->assertSee('Main Street')->assertSee('Check in')->assertDontSee('data-testid="closed"', false);
        $this->get('/c/'.$location->id)->assertNotFound();

        $this->inTenant($a, fn () => $location->update(['is_active' => false]));
        $this->get('/c/'.$location->checkin_public_id)->assertOk()->assertSee('not currently accepting customers');
    }

    public function test_qr_svg_encodes_the_public_checkin_url_and_poster_renders(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        [$a] = $this->twoTenants();
        $this->actingAsTenant($a);
        $location = Location::factory()->create(['name' => 'Harbor']);
        $admin = $this->userIn($a);
        $admin->assignRole(Roles::COMPANY_ADMIN);

        $this->actingAs($admin)->get("/admin/locations/{$location->id}/qr.svg")
            ->assertOk()->assertHeader('Content-Type', 'image/svg+xml');

        $this->actingAs($admin)->get("/admin/locations/{$location->id}/poster")
            ->assertOk()->assertSee('Harbor')->assertSee($location->checkinUrl())->assertSee('<svg', false);
    }
}
