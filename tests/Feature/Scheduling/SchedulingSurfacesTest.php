<?php

namespace Tests\Feature\Scheduling;

use App\Domain\Access\CurrentLocation;
use App\Domain\Access\DeviceContext;
use App\Domain\Access\DevicePairingService;
use App\Domain\Access\Models\Device;
use App\Domain\Access\Roles;
use App\Domain\Queue\CustomerType;
use App\Domain\Queue\Models\Ticket;
use App\Domain\Scheduling\AppointmentStatus;
use App\Domain\Scheduling\Models\Appointment;
use App\Domain\Scheduling\Models\EmployeeSchedule;
use App\Domain\Tenancy\Models\Tenant;
use App\Livewire\Admin\Setup\EmployeeAvailability;
use App\Livewire\PublicSite\BookingPage;
use App\Livewire\PublicSite\KioskCheckin;
use App\Livewire\PublicSite\ManageAppointment;
use App\Livewire\Staff\AppointmentsConsole;
use App\Livewire\Staff\QueueDashboard;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsQueue;
use Tests\Concerns\BuildsSchedule;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class SchedulingSurfacesTest extends TestCase
{
    use BuildsQueue, BuildsSchedule, InteractsWithTenants, RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        [$this->tenant] = $this->twoTenants();
        $this->buildQueue($this->tenant);
        $this->buildSchedule();
        $this->travelTo(CarbonImmutable::parse('2026-10-05 08:00', 'UTC'));
    }

    public function test_public_booking_flow(): void
    {
        $this->scheduledEmployee();
        $this->tenantContext()->clear();
        $this->get('/book/'.$this->location->checkin_public_id)->assertOk()->assertSee('Consultation');
        $this->actingAsTenant($this->tenant);

        Livewire::test(BookingPage::class, ['location' => $this->location->checkin_public_id])
            ->call('chooseService', $this->appt->id)->assertSet('step', 'date')
            ->assertSee('9:00 AM')
            ->call('chooseSlot', '2026-10-05T09:00')
            ->set('name', 'Jane Doe')->set('phone', '(202) 555-0123')
            ->call('submit')
            ->assertSet('step', 'done')->assertSee('You are booked!');

        $a = Appointment::query()->sole();
        $this->assertSame('2026-10-05 09:00', $a->starts_at->format('Y-m-d H:i'));
        $this->assertSame('online', $a->source);
    }

    public function test_booking_last_slot_already_taken_shows_alternatives(): void
    {
        $this->scheduledEmployee('Maria', '09:00', '10:00');
        $page = Livewire::test(BookingPage::class, ['location' => $this->location->checkin_public_id])
            ->call('chooseService', $this->appt->id)->call('chooseSlot', '2026-10-05T09:00')
            ->set('name', 'B')->set('phone', '2025550102');

        $this->book(CarbonImmutable::parse('2026-10-05 09:00', 'UTC'), 'A', '+12025550101'); // someone else wins

        $page->call('submit')->assertSet('step', 'date')->assertSee('was just taken');
    }

    public function test_manage_page_reschedule_and_cancel_and_cutoff(): void
    {
        $this->scheduledEmployee();
        $a = $this->book(CarbonImmutable::parse('2026-10-06 10:00', 'UTC'));
        $this->tenantContext()->clear();
        $this->get('/a/'.$a->manage_token)->assertOk()->assertSee($a->confirmation_code);
        $this->get('/a/nope')->assertNotFound();
        $this->actingAsTenant($this->tenant);

        Livewire::test(ManageAppointment::class, ['appointment' => $a->manage_token])
            ->call('startReschedule')->set('date', '2026-10-06')
            ->call('reschedule', '2026-10-06T11:00')->assertSee('has been moved');
        $this->assertSame('11:00', $a->fresh()->starts_at->format('H:i'));

        $this->travelTo(CarbonImmutable::parse('2026-10-06 10:30', 'UTC'));
        Livewire::test(ManageAppointment::class, ['appointment' => $a->manage_token])
            ->assertSee('too close to your appointment')
            ->call('cancel')->assertSee('too close');
        $this->assertSame(AppointmentStatus::Booked, $a->fresh()->status);
    }

    public function test_sms_checkin_link_checks_in_and_redirects_to_ticket(): void
    {
        $this->scheduledEmployee();
        $a = $this->book(CarbonImmutable::parse('2026-10-05 14:00', 'UTC'));
        $this->travelTo(CarbonImmutable::parse('2026-10-05 13:50', 'UTC'));
        $this->tenantContext()->clear();

        $response = $this->get('/a/'.$a->manage_token.'/checkin');

        $ticket = $this->inTenant($this->tenant, fn () => Ticket::query()->sole());
        $response->assertRedirect(route('public.ticket', $ticket->public_token));
        $this->assertSame(CustomerType::Appointment, $ticket->customer_type);
    }

    public function test_kiosk_appointment_check_in_and_not_found_fallback(): void
    {
        $maria = $this->scheduledEmployee();
        $a = $this->book(CarbonImmutable::parse('2026-10-05 14:00', 'UTC'), employee: $maria);
        $this->travelTo(CarbonImmutable::parse('2026-10-05 13:55', 'UTC'));

        $p = $this->postJson('/devices/pairings', ['type' => 'kiosk'])->json();
        $this->inTenant($this->tenant, fn () => app(DevicePairingService::class)->claim($p['code'], $this->location, 'Kiosk'));
        $this->actingAsTenant($this->tenant);
        app(DeviceContext::class)->set(Device::query()->with('location')->sole());

        Livewire::test(KioskCheckin::class)
            ->call('haveAppointment')->set('lookup', 'XXXXXX')->call('submitAppointment')
            ->assertSee("couldn't find an appointment")
            ->set('lookup', $a->confirmation_code)->call('submitAppointment')
            ->assertSet('step', 'done')->assertSee('A-001');

        $this->assertSame(AppointmentStatus::Arrived, $a->fresh()->status);
        $this->assertSame($maria->id, Ticket::query()->sole()->assigned_employee_id);
    }

    public function test_staff_console_books_with_override_warning(): void
    {
        $maria = $this->scheduledEmployee('Maria', '09:00', '12:00');
        $rita = $this->userIn($this->tenant);
        $rita->assignRole(Roles::RECEPTIONIST);
        $rita->locations()->attach($this->location);
        $this->actingAs($rita);
        app(CurrentLocation::class)->set($this->location);

        $c = Livewire::test(AppointmentsConsole::class)->set('date', '2026-10-06')->call('create')
            ->set('name', 'Walk Late')->set('phone', '2025550155')->set('serviceId', $this->appt->id)
            ->set('employeeId', $maria->id)->set('time', '2026-10-06T18:00')
            ->call('save')->assertSet('needsOverride', true)->assertSee('Book anyway');
        $this->assertSame(0, Appointment::query()->count());

        $c->set('override', true)->call('save')->assertSee('Appointment booked.');
        $this->assertSame('staff', Appointment::query()->sole()->source);
        $this->assertSame($rita->id, Appointment::query()->sole()->created_by);
    }

    public function test_my_availability_menu_link_follows_company_setting(): void
    {
        $maria = $this->scheduledEmployee();
        $maria->user->assignRole(Roles::EMPLOYEE);
        $this->actingAs($maria->user);

        $this->get('/staff')->assertOk()->assertDontSee(route('staff.availability'));

        $this->tenant->forceFill(['settings' => array_merge($this->tenant->settings, ['employees_edit_own_schedule' => true])])->save();
        $this->get('/staff')->assertOk()->assertSee(route('staff.availability'));
    }

    public function test_employee_availability_permissions(): void
    {
        $maria = $this->scheduledEmployee();
        $this->actingAs($maria->user);
        $maria->user->assignRole(Roles::EMPLOYEE);

        Livewire::test(EmployeeAvailability::class)->assertForbidden();

        $this->tenant->forceFill(['settings' => array_merge($this->tenant->settings, ['employees_edit_own_schedule' => true])])->save();
        $this->actingAsTenant($this->tenant->fresh());
        Livewire::test(EmployeeAvailability::class)
            ->set('rows', [['location_id' => $this->location->id, 'weekday' => 1, 'starts_at' => '10:00', 'ends_at' => '14:00']])
            ->call('saveSchedule')->assertHasNoErrors();
        $this->assertSame(1, EmployeeSchedule::query()->where('employee_id', $maria->id)->count());

        $manager = $this->userIn($this->tenant);
        $manager->assignRole(Roles::LOCATION_MANAGER);
        $manager->locations()->attach($this->location);
        $this->actingAs($manager);
        Livewire::test(EmployeeAvailability::class, ['employee' => $maria])
            ->set('offStart', '2026-10-07T09:00')->set('offEnd', '2026-10-07T12:00')->call('addTimeOff')->assertHasNoErrors();
    }

    public function test_dashboard_shows_todays_appointments(): void
    {
        $this->scheduledEmployee();
        $this->book(CarbonImmutable::parse('2026-10-05 15:00', 'UTC'), 'Ann Appt');
        $rita = $this->userIn($this->tenant);
        $rita->assignRole(Roles::RECEPTIONIST);
        $rita->locations()->attach($this->location);
        $this->actingAs($rita);
        app(CurrentLocation::class)->set($this->location);

        Livewire::test(QueueDashboard::class)->assertSee("Today's appointments")->assertSee('Ann Appt')->assertSee('15:00');
    }
}
