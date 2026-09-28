<?php

namespace Tests\Feature\Scheduling;

use App\Domain\Notifications\Models\SmsMessage;
use App\Domain\Queue\Actor;
use App\Domain\Queue\CheckinChannel;
use App\Domain\Queue\CustomerType;
use App\Domain\Queue\Models\Customer;
use App\Domain\Queue\TicketStateMachine;
use App\Domain\Scheduling\AppointmentBooking;
use App\Domain\Scheduling\AppointmentCheckin;
use App\Domain\Scheduling\AppointmentHousekeeping;
use App\Domain\Scheduling\AppointmentStatus;
use App\Domain\Scheduling\Exceptions\BookingNotAllowedException;
use App\Domain\Scheduling\Exceptions\SlotUnavailableException;
use App\Domain\Scheduling\Models\Appointment;
use App\Domain\Scheduling\Models\TimeOff;
use App\Domain\Scheduling\SlotFinder;
use App\Domain\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsQueue;
use Tests\Concerns\BuildsSchedule;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class SchedulingTest extends TestCase
{
    use BuildsQueue, BuildsSchedule, InteractsWithTenants, RefreshDatabase;

    private Tenant $tenant;

    private CarbonImmutable $tomorrow;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->tenant] = $this->twoTenants();
        $this->buildQueue($this->tenant);
        $this->buildSchedule();
        $this->travelTo(CarbonImmutable::parse('2026-10-05 08:00', 'UTC')); // Monday
        $this->tomorrow = CarbonImmutable::parse('2026-10-06', 'UTC');
    }

    private function starts(array $slots): array
    {
        return array_map(fn ($s) => $s->start->format('H:i'), $slots);
    }

    public function test_slots_follow_employee_hours_duration_and_grid(): void
    {
        $this->scheduledEmployee('Maria', '09:00', '11:00');

        $slots = app(SlotFinder::class)->slots($this->location, $this->appt, $this->tomorrow);

        // 25 min + 5 min buffer = 30 min, 15-min grid, last start 10:30.
        $this->assertSame(['09:00', '09:15', '09:30', '09:45', '10:00', '10:15', '10:30'], $this->starts($slots));
    }

    public function test_booked_slot_and_time_off_block_availability(): void
    {
        $maria = $this->scheduledEmployee('Maria', '09:00', '11:00');
        $this->book($this->tomorrow->setTime(9, 30));
        TimeOff::create(['employee_id' => $maria->id, 'starts_at' => $this->tomorrow->setTime(10, 30), 'ends_at' => $this->tomorrow->setTime(11, 0)]);

        $this->assertSame(['09:00', '10:00'], $this->starts(app(SlotFinder::class)->slots($this->location, $this->appt, $this->tomorrow)));
    }

    public function test_capacity_cap_per_hour(): void
    {
        $this->scheduledEmployee('Maria', '10:00', '11:00');
        $this->scheduledEmployee('Sam', '10:00', '11:00');
        $this->location->update(['appointment_capacity_per_hour' => 2]);

        $this->book($this->tomorrow->setTime(10, 0), 'A', '+12025550101');
        $this->book($this->tomorrow->setTime(10, 0), 'B', '+12025550102');

        $slots = app(SlotFinder::class)->slots($this->location->fresh(), $this->appt, $this->tomorrow);
        $this->assertSame([], array_filter($this->starts($slots), fn ($t) => str_starts_with($t, '10:')));
    }

    public function test_lead_time_and_horizon_window(): void
    {
        $this->scheduledEmployee();
        $this->location->update(['booking_lead_minutes' => 120, 'booking_horizon_days' => 30]);
        $this->travelTo(CarbonImmutable::parse('2026-10-06 09:15', 'UTC'));

        $today = app(SlotFinder::class)->slots($this->location->fresh(), $this->appt, $this->tomorrow);
        $this->assertSame('11:15', $this->starts($today)[0]);

        $this->assertSame([], app(SlotFinder::class)->slots($this->location->fresh(), $this->appt, $this->tomorrow->addDays(31)));
    }

    public function test_booking_confirmation_code_manage_token_and_sms(): void
    {
        $this->scheduledEmployee();

        $a = $this->book($this->tomorrow->setTime(10, 0));

        $this->assertSame(AppointmentStatus::Booked, $a->status);
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{6}$/', $a->confirmation_code);
        $this->assertSame(40, strlen($a->manage_token));
        $this->assertSame('10:30', $a->ends_at->format('H:i'));
        $sms = SmsMessage::query()->where('event', 'appointment_confirmation')->sole();
        $this->assertStringContainsString($a->confirmation_code, $sms->body);
        $this->assertStringContainsString($a->manageUrl(), $sms->body);
    }

    public function test_second_booking_of_last_slot_fails_and_any_employee_spreads_load(): void
    {
        $maria = $this->scheduledEmployee('Maria', '10:00', '10:30');
        $this->book($this->tomorrow->setTime(10, 0), 'A', '+12025550101');

        $this->expectException(SlotUnavailableException::class);
        $this->book($this->tomorrow->setTime(10, 0), 'B', '+12025550102');
    }

    public function test_any_employee_booking_picks_least_booked(): void
    {
        $maria = $this->scheduledEmployee('Maria');
        $sam = $this->scheduledEmployee('Sam');
        $first = $this->book($this->tomorrow->setTime(9, 0), 'A', '+12025550101', $maria);
        $second = $this->book($this->tomorrow->setTime(10, 0), 'B', '+12025550102');

        $this->assertSame($maria->id, $first->employee_id);
        $this->assertSame($sam->id, $second->employee_id);
    }

    public function test_staff_can_override_availability_but_online_cannot(): void
    {
        $maria = $this->scheduledEmployee('Maria', '09:00', '10:00');

        try {
            $this->book($this->tomorrow->setTime(18, 0), employee: $maria);
            $this->fail('Online booking outside hours must fail');
        } catch (SlotUnavailableException) {
        }

        $a = $this->book($this->tomorrow->setTime(18, 0), employee: $maria, source: 'staff', override: true);
        $this->assertSame('18:00', $a->starts_at->format('H:i'));
    }

    public function test_reschedule_releases_old_slot_and_cancel_frees_it(): void
    {
        $this->scheduledEmployee('Maria', '10:00', '11:00');
        $a = $this->book($this->tomorrow->setTime(10, 0));
        $booking = app(AppointmentBooking::class);

        $booking->reschedule($a, $this->tomorrow->setTime(10, 30));
        $this->assertContains('10:00', $this->starts(app(SlotFinder::class)->slots($this->location, $this->appt, $this->tomorrow)));
        $this->assertSame(1, SmsMessage::query()->where('event', 'appointment_rescheduled')->count());

        $booking->cancel($a->fresh());
        $this->assertSame(AppointmentStatus::Cancelled, $a->fresh()->status);
        $this->assertContains('10:30', $this->starts(app(SlotFinder::class)->slots($this->location, $this->appt, $this->tomorrow)));
        $this->assertSame(1, SmsMessage::query()->where('event', 'appointment_cancelled')->count());
    }

    public function test_online_changes_blocked_inside_cutoff(): void
    {
        $this->scheduledEmployee();
        $a = $this->book($this->tomorrow->setTime(10, 0));
        $this->travelTo($this->tomorrow->setTime(9, 30)); // 30 min before, cutoff 60

        $this->expectException(BookingNotAllowedException::class);
        app(AppointmentBooking::class)->cancel($a->fresh());
    }

    public function test_no_show_limit_blocks_online_booking(): void
    {
        $this->tenant->forceFill(['settings' => array_merge($this->tenant->settings, ['booking_no_show_limit' => 2])])->save();
        $this->scheduledEmployee();
        Customer::create(['name' => 'Flaky', 'phone' => '+12025550199'])->forceFill(['no_show_count' => 2])->save();
        $this->location->refresh();

        $this->expectException(BookingNotAllowedException::class);
        $this->book($this->tomorrow->setTime(10, 0), 'Flaky', '+12025550199');
    }

    public function test_reminders_are_sent_once_per_offset_and_only_when_due(): void
    {
        $this->scheduledEmployee();
        $this->travelTo(CarbonImmutable::parse('2026-10-04 08:00', 'UTC'));
        $a = $this->book(CarbonImmutable::parse('2026-10-06 10:00', 'UTC'));
        $housekeeping = app(AppointmentHousekeeping::class);

        $this->assertSame(0, $housekeeping->sendReminders());

        $this->travelTo(CarbonImmutable::parse('2026-10-05 10:05', 'UTC'));   // ~24 h before
        $this->assertSame(1, $housekeeping->sendReminders());
        $this->assertSame(0, $housekeeping->sendReminders(), 'idempotent');

        $this->travelTo(CarbonImmutable::parse('2026-10-06 08:05', 'UTC'));   // ~2 h before
        $this->assertSame(1, $housekeeping->sendReminders());
        $this->assertSame(2, SmsMessage::query()->where('event', 'appointment_reminder')->where('appointment_id', $a->id)->count());
    }

    public function test_reminder_not_sent_for_cancelled_appointment(): void
    {
        $this->scheduledEmployee();
        $this->travelTo(CarbonImmutable::parse('2026-10-04 08:00', 'UTC'));
        $a = $this->book(CarbonImmutable::parse('2026-10-06 10:00', 'UTC'));
        app(AppointmentBooking::class)->cancel($a);

        $this->travelTo(CarbonImmutable::parse('2026-10-05 10:05', 'UTC'));
        $this->assertSame(0, app(AppointmentHousekeeping::class)->sendReminders());
    }

    public function test_auto_no_show_after_grace(): void
    {
        $this->scheduledEmployee();
        $a = $this->book($this->tomorrow->setTime(14, 0));

        $this->travelTo($this->tomorrow->setTime(14, 10));
        $this->assertSame(0, app(AppointmentHousekeeping::class)->autoNoShow());
        $this->travelTo($this->tomorrow->setTime(14, 16));
        $this->assertSame(1, app(AppointmentHousekeeping::class)->autoNoShow());

        $this->assertSame(AppointmentStatus::NoShow, $a->fresh()->status);
        $this->assertSame(1, Customer::query()->find($a->customer_id)->no_show_count);
    }

    public function test_check_in_by_code_or_phone_links_ticket_with_priority_and_employee(): void
    {
        $maria = $this->scheduledEmployee();
        $a = $this->book($this->tomorrow->setTime(14, 0), employee: $maria);
        $this->travelTo($this->tomorrow->setTime(13, 50));
        $checkin = app(AppointmentCheckin::class);

        $this->assertTrue($checkin->find($this->location, strtolower($a->confirmation_code))->is($a));
        $this->assertTrue($checkin->find($this->location, '(202) 555-0123')->is($a));
        $this->assertNull($checkin->find($this->location, 'ZZZZZZ'));

        $ticket = $checkin->checkIn($a, CheckinChannel::Kiosk, Actor::customer());

        $this->assertSame(CustomerType::Appointment, $ticket->customer_type);
        $this->assertSame($a->id, $ticket->appointment_id);
        $this->assertSame($maria->id, $ticket->assigned_employee_id);
        $this->assertSame(10, $ticket->priority);
        $this->assertSame(AppointmentStatus::Arrived, $a->fresh()->status);
        $this->assertTrue($checkin->checkIn($a->fresh(), CheckinChannel::Kiosk, Actor::customer())->is($ticket), 'idempotent');
    }

    public function test_late_arrival_gets_no_boost_and_other_day_is_refused(): void
    {
        $this->scheduledEmployee();
        $a = $this->book($this->tomorrow->setTime(14, 0));

        $this->travelTo($this->tomorrow->setTime(14, 40));
        $this->assertSame(0, app(AppointmentCheckin::class)->checkIn($a, CheckinChannel::Qr, Actor::customer())->priority);

        $b = $this->book($this->tomorrow->addDay()->setTime(10, 0), 'Other', '+12025550111');
        $this->expectException(BookingNotAllowedException::class);
        app(AppointmentCheckin::class)->checkIn($b, CheckinChannel::Qr, Actor::customer());
    }

    public function test_appointment_status_follows_ticket(): void
    {
        $maria = $this->scheduledEmployee();
        $a = $this->book($this->tomorrow->setTime(14, 0), employee: $maria);
        $this->travelTo($this->tomorrow->setTime(13, 55));
        $ticket = app(AppointmentCheckin::class)->checkIn($a, CheckinChannel::Receptionist, Actor::system());

        $maria->forceFill(['status' => 'available', 'current_location_id' => $this->location->id])->save();
        $queue = app(TicketStateMachine::class);
        $queue->callNext($maria->fresh(), $this->location, Actor::system());
        $queue->start($ticket, Actor::system());
        $this->assertSame(AppointmentStatus::InService, $a->fresh()->status);

        $queue->complete($ticket, Actor::system());
        $this->assertSame(AppointmentStatus::Completed, $a->fresh()->status);
    }

    public function test_appointments_are_tenant_isolated(): void
    {
        $this->scheduledEmployee();
        $this->book($this->tomorrow->setTime(10, 0));
        $b = Tenant::query()->where('id', '!=', $this->tenant->id)->first();

        $this->assertSame(0, $this->inTenant($b, fn () => Appointment::query()->count()));
    }
}
