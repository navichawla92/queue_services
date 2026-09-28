<?php

namespace Tests\Feature\Access;

use App\Domain\Access\LocationAccess;
use App\Domain\Access\Roles;
use App\Domain\Organization\Models\Location;
use App\Http\Middleware\ResolveCurrentLocation;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class LocationScopeTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_location_manager_is_limited_to_assigned_locations(): void
    {
        [$a] = $this->twoTenants();
        $this->actingAsTenant($a);
        [$x, $y] = Location::factory()->count(2)->create();
        $manager = $this->userIn($a);
        $manager->assignRole(Roles::LOCATION_MANAGER);
        $manager->locations()->attach($x);

        $access = app(LocationAccess::class);
        $this->assertTrue($access->allows($manager, 'queue.manage', $x));
        $this->assertFalse($access->allows($manager, 'queue.manage', $y));

        $this->actingAs($manager)->post("/staff/location/{$y->id}")->assertForbidden();
    }

    public function test_multi_location_receptionist_switches_between_assigned_locations_only(): void
    {
        [$a] = $this->twoTenants();
        $this->actingAsTenant($a);
        [$x, $y, $z] = Location::factory()->count(3)->sequence(['name' => 'X'], ['name' => 'Y'], ['name' => 'Z'])->create();
        $rita = $this->userIn($a);
        $rita->assignRole(Roles::RECEPTIONIST);
        $rita->locations()->attach([$x->id, $y->id]);

        $this->actingAs($rita)->get('/staff')->assertOk()
            ->assertSee('location-switcher', false)->assertSee('X')->assertSee('Y')->assertDontSee('>Z<', false);

        $this->from('/staff')->post("/staff/location/{$y->id}")->assertRedirect('/staff');
        $this->assertSame($y->id, session(ResolveCurrentLocation::SESSION_KEY));

        $this->post("/staff/location/{$z->id}")->assertForbidden();
    }

    public function test_company_admin_and_all_locations_users_reach_every_location(): void
    {
        [$a] = $this->twoTenants();
        $this->actingAsTenant($a);
        $x = Location::factory()->create();
        $admin = $this->userIn($a);
        $admin->assignRole(Roles::COMPANY_ADMIN);
        $roaming = $this->userIn($a, ['all_locations' => true]);
        $roaming->forceFill(['all_locations' => true])->save();
        $roaming->assignRole(Roles::EMPLOYEE);

        $access = app(LocationAccess::class);
        $this->assertTrue($access->canAccess($admin, $x));
        $this->assertTrue($access->canAccess($roaming, $x));
    }

    public function test_switching_to_another_tenants_location_is_not_found(): void
    {
        [$a, $b] = $this->twoTenants();
        $bLocation = $this->inTenant($b, fn () => Location::factory()->create());
        $admin = $this->inTenant($a, function () use ($a) {
            $admin = $this->userIn($a);
            $admin->assignRole(Roles::COMPANY_ADMIN);

            return $admin;
        });

        $this->assertCrossTenantHidden($admin, "/staff/location/{$bLocation->id}", 'post');
    }

    public function test_inactive_locations_are_not_offered(): void
    {
        [$a] = $this->twoTenants();
        $this->actingAsTenant($a);
        Location::factory()->create(['name' => 'Open']);
        Location::factory()->inactive()->create(['name' => 'Closed']);
        $admin = $this->userIn($a);
        $admin->assignRole(Roles::COMPANY_ADMIN);

        $names = app(LocationAccess::class)->accessibleLocations($admin)->pluck('name')->all();
        $this->assertSame(['Open'], $names);
    }
}
