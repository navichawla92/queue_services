<?php

namespace Tests\Feature\Access;

use App\Domain\Access\DevicePairingService;
use App\Domain\Access\Models\Device;
use App\Domain\Access\Roles;
use App\Domain\Organization\Models\Location;
use App\Livewire\Admin\Devices;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class DevicePairingTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /** @return array{code: string, claim: string} */
    private function requestCode(string $type = 'display'): array
    {
        return $this->postJson('/devices/pairings', ['type' => $type])->assertCreated()->json();
    }

    public function test_full_pairing_flow_hands_token_over_once_and_authenticates(): void
    {
        [$a] = $this->twoTenants();
        $location = $this->inTenant($a, fn () => Location::factory()->create(['name' => 'Main']));

        $p = $this->requestCode();
        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{6}$/', $p['code']);
        $this->postJson('/devices/pairings/status', $p)->assertJson(['status' => 'pending']);

        $device = $this->inTenant($a, fn () => app(DevicePairingService::class)->claim(strtolower($p['code']), $location, 'Lobby TV'));

        $token = $this->postJson('/devices/pairings/status', $p)->assertJson(['status' => 'paired'])->json('token');
        $this->assertNotEmpty($token);
        $this->assertSame(Device::hashToken($token), Device::withoutTenantScope()->find($device->id)->token_hash);

        // One-time hand-off.
        $this->postJson('/devices/pairings/status', $p)->assertJson(['status' => 'invalid']);

        $this->tenantContext()->clear();
        $this->withToken($token)->getJson('/device/me')
            ->assertOk()
            ->assertJson(['device' => ['name' => 'Lobby TV', 'type' => 'display'], 'location' => ['name' => 'Main']]);
    }

    public function test_wrong_claim_secret_cannot_collect_token(): void
    {
        [$a] = $this->twoTenants();
        $location = $this->inTenant($a, fn () => Location::factory()->create());
        $p = $this->requestCode();
        $this->inTenant($a, fn () => app(DevicePairingService::class)->claim($p['code'], $location, 'TV'));

        $this->postJson('/devices/pairings/status', ['code' => $p['code'], 'claim' => 'guess'])
            ->assertJson(['status' => 'invalid'])->assertJsonMissing(['token']);
    }

    public function test_expired_code_cannot_be_claimed(): void
    {
        [$a] = $this->twoTenants();
        $location = $this->inTenant($a, fn () => Location::factory()->create());
        $p = $this->requestCode();

        $this->travel(DevicePairingService::TTL_MINUTES + 1)->minutes();

        $this->actingAsTenant($a);
        $this->expectException(ValidationException::class);
        app(DevicePairingService::class)->claim($p['code'], $location, 'TV');
    }

    public function test_revoked_device_is_rejected(): void
    {
        [$a] = $this->twoTenants();
        $this->actingAsTenant($a);
        $location = Location::factory()->create();
        $p = $this->requestCode();
        $device = app(DevicePairingService::class)->claim($p['code'], $location, 'TV');
        $token = $this->postJson('/devices/pairings/status', $p)->json('token');

        app(DevicePairingService::class)->revoke($device);

        $this->tenantContext()->clear();
        $this->withToken($token)->getJson('/device/me')->assertUnauthorized()->assertJson(['paired' => false]);
    }

    public function test_device_of_suspended_tenant_is_unavailable(): void
    {
        [$a] = $this->twoTenants();
        $this->actingAsTenant($a);
        $location = Location::factory()->create();
        $p = $this->requestCode();
        app(DevicePairingService::class)->claim($p['code'], $location, 'TV');
        $token = $this->postJson('/devices/pairings/status', $p)->json('token');
        $a->suspend();

        $this->tenantContext()->clear();
        $this->withToken($token)->getJson('/device/me')->assertStatus(503);
    }

    public function test_device_binds_its_own_tenant_only(): void
    {
        [$a, $b] = $this->twoTenants();
        $location = $this->inTenant($b, fn () => Location::factory()->create());
        $p = $this->requestCode('kiosk');
        $this->inTenant($b, fn () => app(DevicePairingService::class)->claim($p['code'], $location, 'Kiosk'));
        $token = $this->postJson('/devices/pairings/status', $p)->json('token');

        $this->tenantContext()->clear();
        $this->withToken($token)->getJson('/device/me')->assertOk();
        $this->assertSame($b->id, $this->tenantContext()->id());
    }

    public function test_admin_screen_pairs_and_revokes_within_location_scope(): void
    {
        [$a] = $this->twoTenants();
        $this->actingAsTenant($a);
        [$x, $y] = Location::factory()->count(2)->create();
        $manager = $this->userIn($a);
        $manager->assignRole(Roles::LOCATION_MANAGER);
        $manager->locations()->attach($x);
        $this->actingAs($manager);

        $p = $this->requestCode();
        $this->actingAsTenant($a);

        Livewire::test(Devices::class)
            ->set('code', $p['code'])->set('location_id', $y->id)->set('name', 'TV')
            ->call('pair')->assertForbidden();

        Livewire::test(Devices::class)
            ->set('code', $p['code'])->set('location_id', $x->id)->set('name', 'TV')
            ->call('pair')->assertHasNoErrors()->assertSee('TV paired to');

        $device = Device::query()->sole();
        Livewire::test(Devices::class)->call('revoke', $device->id);
        $this->assertTrue($device->fresh()->isRevoked());
        $this->assertNull($device->fresh()->token_hash);
    }

    public function test_employee_cannot_open_device_admin(): void
    {
        [$a] = $this->twoTenants();
        $this->actingAsTenant($a);
        $employee = $this->userIn($a);
        $employee->assignRole(Roles::EMPLOYEE);

        $this->actingAs($employee)->get('/admin/devices')->assertForbidden();
    }

    public function test_shell_pages_render(): void
    {
        $this->get('/display')->assertOk()->assertSee('pairing-code', false);
        $this->get('/kiosk')->assertOk()->assertSee('data-type="kiosk"', false);
    }
}
