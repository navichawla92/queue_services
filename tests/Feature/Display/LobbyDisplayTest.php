<?php

namespace Tests\Feature\Display;

use App\Domain\Access\DevicePairingService;
use App\Domain\Access\Models\Device;
use App\Domain\Access\Roles;
use App\Domain\Display\DisplayChanged;
use App\Domain\Organization\Models\Department;
use App\Domain\Queue\Actor;
use App\Domain\Queue\TicketStateMachine;
use App\Domain\Tenancy\Models\Tenant;
use App\Livewire\Admin\Devices;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\Concerns\BuildsQueue;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class LobbyDisplayTest extends TestCase
{
    use BuildsQueue, InteractsWithTenants, RefreshDatabase;

    private Tenant $tenant;

    private string $token;

    private Device $device;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->tenant] = $this->twoTenants();
        $this->buildQueue($this->tenant);

        $p = $this->postJson('/devices/pairings', ['type' => 'display'])->json();
        $this->device = $this->inTenant($this->tenant, fn () => app(DevicePairingService::class)->claim($p['code'], $this->location, 'Lobby TV'));
        $this->token = $this->postJson('/devices/pairings/status', $p)->json('token');
        $this->actingAsTenant($this->tenant);
    }

    private function snapshot(): array
    {
        $this->tenantContext()->clear();
        $json = $this->withToken($this->token)->getJson('/display/snapshot')->assertOk()->json();
        $this->actingAsTenant($this->tenant);

        return $json;
    }

    public function test_display_app_requires_paired_display_device(): void
    {
        $this->tenantContext()->clear();
        $this->get('/display/app')->assertRedirect('/display');
        $this->withCookie('device_token', $this->token)->get('/display/app')->assertOk()
            ->assertSee('data-testid="lobby-display"', false)
            ->assertSee($this->device->fresh()->channel_key);
    }

    public function test_now_serving_shows_number_and_desk_but_no_names_by_default(): void
    {
        $maria = $this->onShiftEmployee('Maria');
        $this->issue('John Doe');
        app(TicketStateMachine::class)->callNext($maria, $this->location, Actor::system());

        $snap = $this->snapshot();
        $this->assertSame('A-001', $snap['serving'][0]['number']);
        $this->assertSame($maria->fresh()->currentDesk->label, $snap['serving'][0]['desk']);
        $this->assertNull($snap['serving'][0]['name']);
        $this->assertStringNotContainsString('John', json_encode($snap));
    }

    public function test_opt_in_shows_first_name_and_last_initial_only(): void
    {
        $this->tenant->forceFill(['settings' => array_merge($this->tenant->settings, ['show_customer_names_on_display' => true])])->save();
        $this->issue('John Michael Doe');

        $snap = $this->snapshot();
        $this->assertSame('John D.', $snap['waiting'][0]['name']);
        $this->assertStringNotContainsString('Michael', json_encode($snap));
    }

    public function test_waiting_list_truncates_with_more_indicator(): void
    {
        $this->device->update(['config' => ['waiting_rows' => 10]]);
        foreach (range(1, 40) as $i) {
            $this->issue("C$i");
        }

        $snap = $this->snapshot();
        $this->assertCount(10, $snap['waiting']);
        $this->assertSame('A-001', $snap['waiting'][0]['number']);
        $this->assertSame(30, $snap['waiting_more']);
    }

    public function test_recall_changes_call_key_so_display_highlights_again(): void
    {
        $maria = $this->onShiftEmployee();
        $ticket = $this->issue();
        app(TicketStateMachine::class)->callNext($maria, $this->location, Actor::system());
        $first = $this->snapshot()['serving'][0]['call_key'];

        app(TicketStateMachine::class)->recall($ticket, Actor::system());
        $this->assertNotSame($first, $this->snapshot()['serving'][0]['call_key']);
    }

    public function test_department_filter_limits_display(): void
    {
        $loans = Department::factory()->create(['location_id' => $this->location->id, 'prefix' => 'L']);
        $this->issue('General');
        $this->issue('Loans', overrides: ['department' => $loans]);
        $this->device->update(['config' => ['department_ids' => [$loans->id]]]);

        $this->assertSame(['L-001'], array_column($this->snapshot()['waiting'], 'number'));
    }

    public function test_queue_change_and_config_change_ping_the_display_channel(): void
    {
        Event::fake([DisplayChanged::class]);
        $key = $this->device->fresh()->channel_key;

        $this->issue();
        Event::assertDispatched(DisplayChanged::class, fn (DisplayChanged $e) => $e->broadcastOn()->name === 'display.'.$key && $e->reason === 'queue');

        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = $this->userIn($this->tenant);
        $admin->assignRole(Roles::COMPANY_ADMIN);
        $this->actingAs($admin);
        Livewire::test(Devices::class)->call('configure', $this->device->id)
            ->set('config.layout', 'split')->set('config.orientation', 'portrait')->call('saveConfig')->assertHasNoErrors();

        Event::assertDispatched(DisplayChanged::class, fn (DisplayChanged $e) => $e->reason === 'config');
        $snap = $this->snapshot();
        $this->assertSame('split', $snap['config']['layout']);
        $this->assertSame('portrait', $snap['config']['orientation']);
    }

    public function test_revoke_notifies_display_and_rotates_channel(): void
    {
        Event::fake([DisplayChanged::class]);
        $key = $this->device->fresh()->channel_key;

        app(DevicePairingService::class)->revoke($this->device->fresh());

        Event::assertDispatched(DisplayChanged::class, fn (DisplayChanged $e) => $e->broadcastOn()->name === 'display.'.$key && $e->reason === 'revoked');
        $this->assertNull($this->device->fresh()->channel_key);
        $this->tenantContext()->clear();
        $this->withToken($this->token)->getJson('/display/snapshot')->assertUnauthorized();
    }

    public function test_kiosk_token_cannot_read_display_snapshot(): void
    {
        $p = $this->postJson('/devices/pairings', ['type' => 'kiosk'])->json();
        $this->inTenant($this->tenant, fn () => app(DevicePairingService::class)->claim($p['code'], $this->location, 'Kiosk'));
        $kioskToken = $this->postJson('/devices/pairings/status', $p)->json('token');

        $this->tenantContext()->clear();
        $this->withToken($kioskToken)->getJson('/display/snapshot')->assertForbidden();
    }
}
