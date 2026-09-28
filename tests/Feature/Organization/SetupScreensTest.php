<?php

namespace Tests\Feature\Organization;

use App\Domain\Access\Roles;
use App\Domain\Organization\Models\Closure;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Desk;
use App\Domain\Organization\Models\Employee;
use App\Domain\Organization\Models\Location;
use App\Domain\Organization\Models\OpeningHour;
use App\Domain\Organization\Models\Service;
use App\Domain\Tenancy\Models\Tenant;
use App\Livewire\Admin\Setup\ClosuresEditor;
use App\Livewire\Admin\Setup\DepartmentsEditor;
use App\Livewire\Admin\Setup\DesksEditor;
use App\Livewire\Admin\Setup\Employees;
use App\Livewire\Admin\Setup\HoursEditor;
use App\Livewire\Admin\Setup\Locations;
use App\Livewire\Admin\Setup\LocationServicesEditor;
use App\Livewire\Admin\Setup\Services;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class SetupScreensTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private Tenant $tenant;

    private Location $x;

    private Location $y;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        [$this->tenant] = $this->twoTenants();
        $this->actingAsTenant($this->tenant);
        [$this->x, $this->y] = Location::factory()->count(2)->sequence(['name' => 'X'], ['name' => 'Y'])->create();
    }

    private function as(string $role, array $locations = []): User
    {
        $user = $this->userIn($this->tenant);
        $user->assignRole($role);
        $user->locations()->attach(collect($locations)->pluck('id'));
        $this->actingAs($user);

        return $user;
    }

    public function test_company_admin_creates_edits_and_deactivates_location(): void
    {
        $this->as(Roles::COMPANY_ADMIN);

        Livewire::test(Locations::class)
            ->call('create')
            ->set('name', 'Downtown')->set('timezone', 'America/Chicago')->set('walkin_cutoff_minutes', 20)
            ->call('save')->assertHasNoErrors();

        $downtown = Location::query()->where('name', 'Downtown')->sole();
        $this->assertSame('America/Chicago', $downtown->timezone);

        Livewire::test(Locations::class)->call('setActive', $downtown->id, false);
        $this->assertFalse($downtown->fresh()->is_active);
    }

    public function test_location_manager_can_edit_own_location_but_not_create_or_deactivate(): void
    {
        $this->as(Roles::LOCATION_MANAGER, [$this->x]);

        Livewire::test(Locations::class)->call('create')->assertForbidden();
        Livewire::test(Locations::class)->call('setActive', $this->x->id, false)->assertForbidden();
        Livewire::test(Locations::class)->call('edit', $this->y->id)->assertForbidden();

        Livewire::test(Locations::class)->call('edit', $this->x->id)->set('name', 'X Main')->call('save')->assertHasNoErrors();
        $this->assertSame('X Main', $this->x->fresh()->name);
    }

    public function test_invalid_timezone_is_rejected(): void
    {
        $this->as(Roles::COMPANY_ADMIN);

        Livewire::test(Locations::class)->call('create')->set('name', 'Z')->set('timezone', 'Mars/Olympus')
            ->call('save')->assertHasErrors('timezone');
    }

    public function test_departments_editor_enforces_unique_prefix_and_location_scope(): void
    {
        $this->as(Roles::LOCATION_MANAGER, [$this->x]);

        Livewire::test(DepartmentsEditor::class, ['locationId' => $this->x->id])
            ->set('name', 'Loans')->set('prefix', 'l')->call('save')->assertHasNoErrors();
        $this->assertSame('L', Department::query()->sole()->prefix);

        Livewire::test(DepartmentsEditor::class, ['locationId' => $this->x->id])
            ->set('name', 'Lending')->set('prefix', 'L')->call('save')->assertHasErrors(['prefix' => 'unique']);

        Livewire::test(DepartmentsEditor::class, ['locationId' => $this->y->id])->assertForbidden();
    }

    public function test_desks_editor_rejects_department_of_other_location(): void
    {
        $this->as(Roles::COMPANY_ADMIN);
        $yDept = Department::factory()->create(['location_id' => $this->y->id]);

        Livewire::test(DesksEditor::class, ['locationId' => $this->x->id])
            ->set('label', 'Desk 1')->set('department_id', $yDept->id)->call('save')->assertHasErrors('department_id');

        Livewire::test(DesksEditor::class, ['locationId' => $this->x->id])
            ->set('label', 'Desk 1')->call('save')->assertHasNoErrors();
        $this->assertSame('Desk 1', Desk::query()->sole()->label);
    }

    public function test_services_offered_per_location_with_default_department(): void
    {
        $this->as(Roles::LOCATION_MANAGER, [$this->x]);
        $dept = Department::factory()->create(['location_id' => $this->x->id]);
        $yDept = Department::factory()->create(['location_id' => $this->y->id]);
        $service = Service::factory()->create();

        Livewire::test(LocationServicesEditor::class, ['locationId' => $this->x->id])
            ->set("offered.{$service->id}", $yDept->id)->call('save')->assertHasErrors();

        Livewire::test(LocationServicesEditor::class, ['locationId' => $this->x->id])
            ->set("offered.{$service->id}", $dept->id)->call('save')->assertHasNoErrors();
        $this->assertSame($dept->id, $this->x->services()->sole()->pivot->department_id);

        Livewire::test(LocationServicesEditor::class, ['locationId' => $this->x->id])
            ->set("offered.{$service->id}", '')->call('save');
        $this->assertSame(0, $this->x->services()->count());
    }

    public function test_service_catalog_is_editable_only_company_wide(): void
    {
        $this->as(Roles::LOCATION_MANAGER, [$this->x]);
        Livewire::test(Services::class)->call('create')->assertForbidden();

        $this->as(Roles::COMPANY_ADMIN);
        Livewire::test(Services::class)->call('create')
            ->set('name', 'Account Opening')->set('expected_minutes', 20)->set('allow_appointment', true)
            ->call('save')->assertHasNoErrors();
        $this->assertSame(20, Service::query()->sole()->expected_minutes);

        Livewire::test(Services::class)->call('create')
            ->set('name', 'Nothing')->set('allow_walk_in', false)->set('allow_appointment', false)
            ->call('save')->assertHasErrors('allow_walk_in');
    }

    public function test_hours_editor_saves_weekly_rows_and_validates_order(): void
    {
        $this->as(Roles::LOCATION_MANAGER, [$this->x]);

        Livewire::test(HoursEditor::class, ['locationId' => $this->x->id])
            ->call('addRow')->set('rows.0.opens_at', '17:00')->set('rows.0.closes_at', '09:00')
            ->call('save')->assertHasErrors('rows.0.closes_at');

        Livewire::test(HoursEditor::class, ['locationId' => $this->x->id])
            ->call('addRow')->set('rows.0.weekday', 2)
            ->call('addRow')->set('rows.1.weekday', 3)->set('rows.1.opens_at', '10:00')
            ->call('save')->assertHasNoErrors();

        $this->assertSame(2, OpeningHour::query()->where('location_id', $this->x->id)->whereNull('department_id')->count());

        // Saving again replaces rather than appends.
        Livewire::test(HoursEditor::class, ['locationId' => $this->x->id])->call('removeRow', 0)->call('save');
        $this->assertSame([3], OpeningHour::query()->pluck('weekday')->all());
    }

    public function test_closures_editor_adds_holiday_and_special_hours(): void
    {
        $this->as(Roles::LOCATION_MANAGER, [$this->x]);

        Livewire::test(ClosuresEditor::class, ['locationId' => $this->x->id])
            ->set('date', now()->addDays(3)->toDateString())->set('reason', 'Holiday')->call('add')->assertHasNoErrors()
            ->set('date', now()->addDays(3)->toDateString())->call('add')->assertHasErrors('date');

        Livewire::test(ClosuresEditor::class, ['locationId' => $this->x->id])
            ->set('date', now()->addDays(4)->toDateString())->set('specialHours', true)
            ->set('opens_at', '12:00')->set('closes_at', '11:00')->call('add')->assertHasErrors('closes_at');

        $this->assertTrue(Closure::query()->sole()->isClosedAllDay());
    }

    public function test_company_admin_creates_staff_member_and_invitation_is_sent(): void
    {
        Notification::fake();
        $this->as(Roles::COMPANY_ADMIN);
        $dept = Department::factory()->create(['location_id' => $this->x->id]);
        $desk = Desk::factory()->create(['location_id' => $this->x->id]);
        $service = Service::factory()->create();

        Livewire::test(Employees::class)->call('create')
            ->set('name', 'Maria Lopez')->set('email', 'Maria@Example.com')->set('role', Roles::EMPLOYEE)
            ->set('location_ids', [$this->x->id])->set('display_name', 'Maria')
            ->set('department_ids', [$dept->id])->set('service_ids', [$service->id])->set('default_desk_id', $desk->id)
            ->call('save')->assertHasNoErrors()->assertSee('Invitation sent');

        $employee = Employee::query()->with('user')->sole();
        $this->assertSame('maria@example.com', $employee->user->email);
        $this->assertSame($this->tenant->id, $employee->user->tenant_id);
        $this->assertTrue($employee->user->hasRole(Roles::EMPLOYEE));
        $this->assertSame([$this->x->id], $employee->user->locations()->pluck('locations.id')->all());
        $this->assertSame([$dept->id], $employee->departments()->pluck('departments.id')->all());
        $this->assertSame($desk->id, $employee->default_desk_id);
        Notification::assertSentTo($employee->user, ResetPassword::class);
    }

    public function test_department_must_belong_to_employee_locations(): void
    {
        $this->as(Roles::COMPANY_ADMIN);
        $yDept = Department::factory()->create(['location_id' => $this->y->id]);

        Livewire::test(Employees::class)->call('create')
            ->set('name', 'A')->set('email', 'a@example.com')->set('location_ids', [$this->x->id])
            ->set('display_name', 'A')->set('department_ids', [$yDept->id])
            ->call('save')->assertHasErrors('department_ids.0');
    }

    public function test_location_manager_edits_skills_but_cannot_create_accounts_or_touch_other_locations(): void
    {
        $xDept = Department::factory()->create(['location_id' => $this->x->id]);
        $yDept = Department::factory()->create(['location_id' => $this->y->id]);
        $employee = Employee::factory()->create();
        $employee->user->locations()->attach([$this->x->id, $this->y->id]);
        $employee->departments()->attach($yDept);
        $outsider = Employee::factory()->create();
        $outsider->user->locations()->attach($this->y->id);

        $this->as(Roles::LOCATION_MANAGER, [$this->x]);

        Livewire::test(Employees::class)->call('create')->assertForbidden();
        Livewire::test(Employees::class)->call('edit', $outsider->id)->assertForbidden();
        Livewire::test(Employees::class)->call('setActive', $employee->id, false)->assertForbidden();

        Livewire::test(Employees::class)->call('edit', $employee->id)
            ->set('display_name', 'Sam')->set('department_ids', [$xDept->id])
            ->call('save')->assertHasNoErrors();

        // Their Y department (not manageable by this manager) is kept.
        $this->assertEqualsCanonicalizing([$xDept->id, $yDept->id], $employee->departments()->pluck('departments.id')->all());
        $this->assertSame('Sam', $employee->fresh()->display_name);
    }

    public function test_deactivating_staff_member_ends_their_access(): void
    {
        $this->as(Roles::COMPANY_ADMIN);
        $employee = Employee::factory()->create();

        Livewire::test(Employees::class)->call('setActive', $employee->id, false);

        $this->assertFalse($employee->user->fresh()->is_active);
    }

    public function test_admin_cannot_change_own_role(): void
    {
        $me = $this->as(Roles::COMPANY_ADMIN);
        $mine = Employee::create(['user_id' => $me->id, 'display_name' => 'Me']);

        Livewire::test(Employees::class)->call('edit', $mine->id)->set('role', Roles::EMPLOYEE)
            ->call('save')->assertHasErrors('role');
        $this->assertTrue($me->fresh()->hasRole(Roles::COMPANY_ADMIN));
    }
}
