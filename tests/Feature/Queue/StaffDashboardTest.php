<?php

namespace Tests\Feature\Queue;

use App\Domain\Access\CurrentLocation;
use App\Domain\Access\Roles;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Employee;
use App\Domain\Organization\Models\Location;
use App\Domain\Queue\Actor;
use App\Domain\Queue\Broadcasting\StaffQueueChanged;
use App\Domain\Queue\Models\Ticket;
use App\Domain\Queue\QueueHousekeeping;
use App\Domain\Queue\TicketStateMachine;
use App\Domain\Queue\TicketStatus;
use App\Domain\Tenancy\Models\Tenant;
use App\Livewire\Staff\QueueDashboard;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\Concerns\BuildsQueue;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class StaffDashboardTest extends TestCase
{
    use BuildsQueue, InteractsWithTenants, RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        [$this->tenant] = $this->twoTenants();
        $this->buildQueue($this->tenant);
    }

    private function signIn(Employee|User $who, string $role): User
    {
        $user = $who instanceof Employee ? $who->user : $who;
        $user->assignRole($role);
        $user->locations()->syncWithoutDetaching([$this->location->id]);
        $this->actingAs($user);
        app(CurrentLocation::class)->set($this->location);

        return $user;
    }

    public function test_dashboard_lists_active_tickets_with_spec_columns(): void
    {
        $maria = $this->onShiftEmployee('Maria');
        $this->signIn($maria, Roles::EMPLOYEE);
        $this->issue('John Doe');

        $this->get('/staff')->assertOk()
            ->assertSee('data-testid="queue-dashboard"', false)
            ->assertSee('A-001')->assertSee('John Doe')->assertSee('General help')->assertSee('General')
            ->assertSee('Walk-in')->assertSee('Waiting');
    }

    public function test_employee_calls_next_starts_and_completes(): void
    {
        $maria = $this->onShiftEmployee('Maria');
        $this->signIn($maria, Roles::EMPLOYEE);
        $ticket = $this->issue('John Doe');

        $c = Livewire::test(QueueDashboard::class)->call('callNext')->assertSee('Called A-001 (John Doe).');
        $c->call('start', $ticket->id);
        $c->call('openDialog', 'complete', $ticket->id)->set('text', 'Resolved')->call('confirmDialog')->assertSee('A-001 completed.');

        $this->assertSame(TicketStatus::Completed, $ticket->fresh()->status);
        $this->assertSame('Resolved', $ticket->fresh()->outcome);
    }

    public function test_call_next_with_empty_queue_says_so(): void
    {
        $this->signIn($this->onShiftEmployee(), Roles::EMPLOYEE);

        Livewire::test(QueueDashboard::class)->call('callNext')->assertSee('No one is waiting for you right now.');
    }

    public function test_employee_cannot_act_on_someone_elses_ticket_or_assign(): void
    {
        $maria = $this->onShiftEmployee('Maria');
        $sam = $this->onShiftEmployee('Sam');
        $ticket = $this->issue();
        app(TicketStateMachine::class)->callNext($sam, $this->location, Actor::system());

        $this->signIn($maria, Roles::EMPLOYEE);
        Livewire::test(QueueDashboard::class)->call('start', $ticket->id)->assertForbidden();
        Livewire::test(QueueDashboard::class)->call('openDialog', 'assign', $ticket->id)->assertForbidden();
    }

    public function test_receptionist_assigns_transfers_holds_releases_and_calls_for_employee(): void
    {
        $maria = $this->onShiftEmployee('Maria');
        $rita = $this->signIn($this->userIn($this->tenant, ['name' => 'Rita']), Roles::RECEPTIONIST);
        $loans = Department::factory()->create(['location_id' => $this->location->id, 'prefix' => 'L', 'name' => 'Loans']);
        $ticket = $this->issue('John');

        $c = Livewire::test(QueueDashboard::class);
        $c->call('openDialog', 'assign', $ticket->id)->set('targetEmployeeId', $maria->id)->call('confirmDialog')->assertSee('assigned to Maria');
        $this->assertSame($maria->id, $ticket->fresh()->assigned_employee_id);

        $c->call('openDialog', 'transfer', $ticket->id)->set('targetDepartmentId', $loans->id)->set('text', 'needs loan officer')->call('confirmDialog');
        $this->assertSame($loans->id, $ticket->fresh()->department_id);
        $this->assertSame('needs loan officer', $ticket->fresh()->notes()->sole()->body);
        $this->assertSame($rita->id, $ticket->fresh()->notes()->sole()->author_id);

        $c->call('openDialog', 'hold', $ticket->id)->set('text', 'getting ID')->call('confirmDialog');
        $this->assertSame(TicketStatus::OnHold, $ticket->fresh()->status);
        $c->call('release', $ticket->id);

        $c->call('openDialog', 'call', $ticket->id)->set('targetEmployeeId', $maria->id)->call('confirmDialog')->assertSee('Called A-001 to Maria.');
        $this->assertSame($maria->id, $ticket->fresh()->serving_employee_id);
    }

    public function test_invalid_action_shows_error_not_exception(): void
    {
        $this->signIn($this->userIn($this->tenant), Roles::RECEPTIONIST);
        $ticket = $this->issue();

        Livewire::test(QueueDashboard::class)->call('start', $ticket->id)->assertSee('cannot be moved to');
    }

    public function test_notes_are_internal_and_flagged_on_dashboard(): void
    {
        $maria = $this->onShiftEmployee('Maria');
        $this->signIn($maria, Roles::EMPLOYEE);
        $ticket = $this->issue();

        Livewire::test(QueueDashboard::class)
            ->call('openDialog', 'note', $ticket->id)->set('text', 'Prefers Spanish')->call('confirmDialog')
            ->assertSee('📝1');

        $this->tenantContext()->clear();
        $this->get('/t/'.$ticket->public_token)->assertDontSee('Prefers Spanish');
    }

    public function test_filters_and_my_queue(): void
    {
        $maria = $this->onShiftEmployee('Maria');
        $loans = Department::factory()->create(['location_id' => $this->location->id, 'prefix' => 'L']);
        $this->signIn($maria, Roles::EMPLOYEE);
        $this->issue('General One');
        $this->issue('Loan One', overrides: ['department' => $loans]);

        Livewire::test(QueueDashboard::class)->set('filterDepartment', $loans->id)
            ->assertSee('Loan One')->assertDontSee('General One');
        Livewire::test(QueueDashboard::class)->set('mine', true)
            ->assertSee('General One')->assertDontSee('Loan One');
    }

    public function test_other_location_tickets_are_not_actionable(): void
    {
        $other = Location::factory()->create();
        $this->signIn($this->userIn($this->tenant), Roles::RECEPTIONIST);
        $ticket = $this->issue();
        $ticket->forceFill(['location_id' => $other->id])->save();

        $this->expectException(ModelNotFoundException::class); // 404 over HTTP
        Livewire::test(QueueDashboard::class)->call('noShow', $ticket->id);
    }

    public function test_snapshot_json_has_version_tickets_and_staff(): void
    {
        $maria = $this->onShiftEmployee('Maria');
        $this->signIn($maria, Roles::EMPLOYEE);
        $this->issue('John');

        $this->getJson('/staff/queue/snapshot')->assertOk()
            ->assertJsonPath('version', 1)
            ->assertJsonPath('tickets.0.number', 'A-001')
            ->assertJsonPath('tickets.0.position', 1)
            ->assertJsonPath('staff.0.name', 'Maria');
    }

    public function test_staff_channel_payload_has_no_customer_data(): void
    {
        Event::fake([StaffQueueChanged::class]);
        $this->issue('Secret Name', '+12025550111');

        Event::assertDispatched(StaffQueueChanged::class, function (StaffQueueChanged $e) {
            $json = json_encode($e->broadcastWith());

            return $e->broadcastOn()->name === 'private-tenant.'.$this->tenant->id.'.location.'.$this->location->id.'.queue'
                && ! str_contains($json, 'Secret') && ! str_contains($json, '555');
        });
    }

    public function test_private_channel_authorization_by_tenant_and_location(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'k', 'broadcasting.connections.reverb.secret' => 's', 'broadcasting.connections.reverb.app_id' => '1',
        ]);
        // Channels register on the broadcaster that is default at boot (null in tests).
        Broadcast::purge();
        require base_path('routes/channels.php');
        $channel = 'private-'.StaffQueueChanged::channelName($this->tenant->id, $this->location->id);
        $otherLocation = Location::factory()->create();

        $maria = $this->onShiftEmployee('Maria');
        $this->signIn($maria, Roles::EMPLOYEE);
        $this->tenantContext()->clear();
        $this->post('/broadcasting/auth', ['channel_name' => $channel, 'socket_id' => '123.456'])->assertOk()->assertJsonStructure(['auth']);

        $this->post('/broadcasting/auth', ['channel_name' => 'private-'.StaffQueueChanged::channelName($this->tenant->id, $otherLocation->id), 'socket_id' => '123.456'])
            ->assertForbidden();

        [, $b] = [$this->tenant, Tenant::query()->where('id', '!=', $this->tenant->id)->first()];
        $outsider = $this->inTenant($b, function () use ($b) {
            $u = $this->userIn($b);
            $u->assignRole(Roles::COMPANY_ADMIN);

            return $u;
        });
        $this->actingAs($outsider);
        $this->tenantContext()->clear();
        $this->post('/broadcasting/auth', ['channel_name' => $channel, 'socket_id' => '123.456'])->assertForbidden();
    }

    public function test_auto_no_show_and_end_of_day_closeout(): void
    {
        $this->tenant->forceFill(['settings' => array_merge($this->tenant->settings, ['auto_no_show_minutes' => 5, 'closeout_buffer_minutes' => 30])])->save();
        $this->actingAsTenant($this->tenant->fresh());
        $maria = $this->onShiftEmployee();
        $called = $this->issue('Called');
        $waiting = $this->issue('Waiting');
        app(TicketStateMachine::class)->callNext($maria, $this->location, Actor::system());

        $this->travel(4)->minutes();
        $this->assertSame(0, app(QueueHousekeeping::class)->autoNoShow());
        $this->travel(2)->minutes();
        $this->assertSame(1, app(QueueHousekeeping::class)->autoNoShow());
        $this->assertSame(TicketStatus::NoShow, $called->fresh()->status);

        // Not closed during the day; closed the next day.
        $this->assertSame(0, app(QueueHousekeeping::class)->closeout());
        $this->travel(1)->days();
        $this->assertSame(1, app(QueueHousekeeping::class)->closeout());
        $this->assertSame(TicketStatus::ClosedUnserved, $waiting->fresh()->status);
        $this->assertSame(0, Ticket::query()->active()->count());
    }
}
