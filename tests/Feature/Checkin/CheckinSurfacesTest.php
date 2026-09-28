<?php

namespace Tests\Feature\Checkin;

use App\Domain\Access\CurrentLocation;
use App\Domain\Access\DeviceContext;
use App\Domain\Access\DevicePairingService;
use App\Domain\Access\Models\Device;
use App\Domain\Access\Roles;
use App\Domain\Organization\Models\Employee;
use App\Domain\Organization\Models\OpeningHour;
use App\Domain\Organization\Models\Service;
use App\Domain\Queue\Actor;
use App\Domain\Queue\Broadcasting\LocationPulse;
use App\Domain\Queue\Broadcasting\TicketUpdated;
use App\Domain\Queue\CheckinChannel;
use App\Domain\Queue\Models\Ticket;
use App\Domain\Queue\TicketStateMachine;
use App\Domain\Queue\TicketStatus;
use App\Domain\Tenancy\Models\Tenant;
use App\Livewire\PublicSite\KioskCheckin;
use App\Livewire\PublicSite\MobileCheckin;
use App\Livewire\PublicSite\TicketStatusPage;
use App\Livewire\Staff\ReceptionistCheckin;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\BuildsQueue;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class CheckinSurfacesTest extends TestCase
{
    use BuildsQueue, InteractsWithTenants, RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->tenant] = $this->twoTenants();
        $this->buildQueue($this->tenant);
        $this->onShiftEmployee();
    }

    private function mobile(string $query = ''): Testable
    {
        $this->actingAsTenant($this->tenant);

        return Livewire::withQueryParams($query ? ['src' => $query] : [])
            ->test(MobileCheckin::class, ['location' => $this->location->checkin_public_id]);
    }

    public function test_mobile_page_renders_by_public_id_and_404s_otherwise(): void
    {
        $this->tenantContext()->clear();
        $this->get('/c/'.$this->location->checkin_public_id)->assertOk()->assertSee('Check in');
        $this->get('/c/'.$this->location->id)->assertNotFound();
    }

    public function test_qr_check_in_creates_ticket_and_redirects_to_status_page(): void
    {
        $component = $this->mobile('qr')
            ->call('begin')->assertSet('step', 'service')
            ->call('chooseService', $this->service->id)
            ->set('name', 'John Doe')->set('phone', '(202) 555-0147')->set('smsConsent', true)
            ->call('submit');

        $ticket = Ticket::query()->sole();
        $component->assertRedirect(route('public.ticket', $ticket->public_token));
        $this->assertSame(CheckinChannel::Qr, $ticket->channel);
        $this->assertSame('+12025550147', $ticket->customer_phone);
        $this->assertTrue($ticket->sms_consent);
        $this->assertSame('A-001', $ticket->number);
    }

    public function test_invalid_phone_is_rejected_and_no_ticket_created(): void
    {
        $this->mobile()->call('begin')->call('chooseService', $this->service->id)
            ->set('name', 'X')->set('phone', '12345')->call('submit')
            ->assertHasErrors('phone');

        $this->assertSame(0, Ticket::query()->count());
    }

    public function test_phone_can_be_required_by_tenant_setting(): void
    {
        $this->tenant->forceFill(['settings' => array_merge($this->tenant->settings, ['checkin_phone_required' => true])])->save();

        $this->mobile()->call('begin')->call('chooseService', $this->service->id)
            ->set('name', 'X')->call('submit')->assertHasErrors('phone');
    }

    public function test_sms_consent_declined_means_no_consent_on_ticket(): void
    {
        $this->mobile()->call('begin')->call('chooseService', $this->service->id)
            ->set('name', 'Jane')->set('phone', '2025550148')->set('smsConsent', false)->call('submit');

        $this->assertFalse(Ticket::query()->sole()->sms_consent);
    }

    public function test_duplicate_check_in_shows_existing_ticket(): void
    {
        $first = $this->issue('Jane', '+12025550149');

        $this->mobile()->call('begin')->call('chooseService', $this->service->id)
            ->set('name', 'Jane')->set('phone', '+1 202 555 0149')->call('submit')
            ->assertRedirect(route('public.ticket', $first->public_token));

        $this->assertSame(1, Ticket::query()->count());
    }

    public function test_closed_location_shows_next_opening_and_inactive_location_message(): void
    {
        OpeningHour::query()->delete();
        OpeningHour::create(['location_id' => $this->location->id, 'weekday' => now()->addDay()->dayOfWeek, 'opens_at' => '09:00', 'closes_at' => '17:00']);

        $this->mobile()->assertSet('step', 'closed')->assertSee('We open again');

        $this->location->update(['is_active' => false]);
        $this->mobile()->assertSet('step', 'closed')->assertSee('not currently accepting customers');
    }

    public function test_only_customer_selectable_walk_in_services_are_offered(): void
    {
        $hidden = Service::factory()->create(['name' => 'Internal', 'customer_selectable' => false]);
        $hidden->offerAt($this->location, $this->dept);

        $this->mobile()->call('begin')->assertSee('General help')->assertDontSee('Internal')
            ->call('chooseService', $hidden->id)->assertNotFound();
    }

    public function test_spanish_language_switch(): void
    {
        $this->mobile()->call('setLocale', 'es')->assertSee('Bienvenido')->call('begin')->assertSee('¿En qué podemos ayudarle?');
    }

    public function test_kiosk_requires_pairing_and_issues_kiosk_ticket_then_resets(): void
    {
        $this->tenantContext()->clear();
        $this->get('/kiosk/app')->assertRedirect('/kiosk');

        $p = $this->postJson('/devices/pairings', ['type' => 'kiosk'])->json();
        $this->inTenant($this->tenant, fn () => app(DevicePairingService::class)->claim($p['code'], $this->location, 'Lobby kiosk'));
        $token = $this->postJson('/devices/pairings/status', $p)->json('token');

        $this->tenantContext()->clear();
        $this->withCookie('device_token', $token)->get('/kiosk/app')->assertOk()->assertSee('data-testid="kiosk"', false)->assertSee('Welcome');

        // Component behaviour with the device bound as the middleware would.
        $device = Device::withoutTenantScope()->with('location')->sole();
        $this->actingAsTenant($this->tenant);
        app(DeviceContext::class)->set($device);

        Livewire::test(KioskCheckin::class)
            ->call('toggleLargeText')->assertSet('largeText', true)
            ->call('begin')->call('chooseService', $this->service->id)
            ->set('name', 'Kim')->call('submit')
            ->assertSet('step', 'done')->assertSee('A-001')->assertSee('You are number 1 in line.')
            ->call('idleReset')
            ->assertSet('step', 'start')->assertSet('name', '')->assertSet('largeText', false);

        $this->assertSame(CheckinChannel::Kiosk, Ticket::query()->sole()->channel);
    }

    public function test_status_page_live_states_and_leave_queue(): void
    {
        $ticket = $this->issue('Jane');
        $this->tenantContext()->clear();
        $this->get('/t/'.$ticket->public_token)->assertOk()->assertSee('A-001')->assertSee('You are number 1 in line.');
        $this->get('/t/not-a-real-token')->assertNotFound();

        $this->actingAsTenant($this->tenant);
        $page = Livewire::test(TicketStatusPage::class, ['ticket' => $ticket->public_token]);

        $employee = Employee::query()->with('currentDesk')->sole();
        app(TicketStateMachine::class)->callNext($employee, $this->location, Actor::system());
        $page->call('$refresh')->assertSee("It's your turn!")->assertSee('Please go to '.$employee->currentDesk->label);

        $other = $this->issue('Other');
        Livewire::test(TicketStatusPage::class, ['ticket' => $other->public_token])->call('leaveQueue');
        $this->assertSame(TicketStatus::Cancelled, $other->fresh()->status);
    }

    public function test_queue_changes_broadcast_to_ticket_and_location_pulse_channels(): void
    {
        Event::fake([TicketUpdated::class, LocationPulse::class]);

        $ticket = $this->issue('Jane');

        Event::assertDispatched(TicketUpdated::class, fn (TicketUpdated $e) => $e->broadcastOn()->name === 'ticket.'.$ticket->public_token
            && ! array_key_exists('customer_name', $e->broadcastWith()['ticket']));
        Event::assertDispatched(LocationPulse::class, fn (LocationPulse $e) => $e->broadcastOn()->name === 'location.'.$this->location->checkin_public_id.'.pulse');
    }

    public function test_receptionist_checks_in_staff_only_service_with_assignment(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $staffOnly = Service::factory()->create(['name' => 'Back office', 'customer_selectable' => false]);
        $staffOnly->offerAt($this->location, $this->dept);
        $rita = $this->userIn($this->tenant);
        $rita->assignRole(Roles::RECEPTIONIST);
        $rita->locations()->attach($this->location);
        $this->actingAs($rita);
        app(CurrentLocation::class)->set($this->location);
        $employee = Employee::query()->sole();

        Livewire::test(ReceptionistCheckin::class)
            ->set('name', 'Walk In')->set('serviceId', $staffOnly->id)->set('assignedEmployeeId', $employee->id)
            ->call('save')->assertHasNoErrors()->assertSee('Checked in: ticket A-001');

        $ticket = Ticket::query()->sole();
        $this->assertSame(CheckinChannel::Receptionist, $ticket->channel);
        $this->assertSame($employee->id, $ticket->assigned_employee_id);
    }
}
