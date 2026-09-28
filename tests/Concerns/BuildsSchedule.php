<?php

namespace Tests\Concerns;

use App\Domain\Organization\Models\Employee;
use App\Domain\Organization\Models\Service;
use App\Domain\Scheduling\AppointmentBooking;
use App\Domain\Scheduling\BookingRequest;
use App\Domain\Scheduling\Models\Appointment;
use App\Domain\Scheduling\Models\EmployeeSchedule;
use DateTimeInterface;

/** Scheduling helpers on top of BuildsQueue (UTC location, open all week). */
trait BuildsSchedule
{
    protected Service $appt;

    protected function buildSchedule(): void
    {
        $this->appt = Service::factory()->create(['name' => 'Consultation', 'expected_minutes' => 25, 'allow_appointment' => true]);
        $this->appt->offerAt($this->location, $this->dept);
        $this->location->update(['booking_lead_minutes' => 0, 'booking_buffer_minutes' => 5]);
        $this->location->refresh();
    }

    /** Employee who works 09:00–17:00 every day at the location and serves the appointment service. */
    protected function scheduledEmployee(string $name = 'Maria', string $from = '09:00', string $to = '17:00'): Employee
    {
        $employee = $this->onShiftEmployee($name, [$this->appt, $this->service], start: false);
        foreach (range(0, 6) as $day) {
            EmployeeSchedule::create(['employee_id' => $employee->id, 'location_id' => $this->location->id, 'weekday' => $day, 'starts_at' => $from, 'ends_at' => $to]);
        }

        return $employee;
    }

    protected function book(DateTimeInterface $start, string $name = 'Jane Doe', ?string $phone = '+12025550123', ?Employee $employee = null, string $source = 'online', bool $override = false): Appointment
    {
        return app(AppointmentBooking::class)->book(new BookingRequest(
            $this->location, $this->appt, $start, $name, $phone, null, $phone !== null, $employee, $source, $override,
        ));
    }
}
