<?php

namespace App\Domain\Scheduling;

use App\Domain\Notifications\NotificationDispatcher;
use App\Domain\Notifications\NotificationEvent;
use App\Domain\Notifications\SmsContext;
use App\Domain\Scheduling\Models\Appointment;
use Illuminate\Support\Str;

/** Appointment SMS (confirmation, reschedule, cancellation, reminder). */
class AppointmentNotifier
{
    public function __construct(private readonly NotificationDispatcher $dispatcher) {}

    public function confirmed(Appointment $appointment): void
    {
        $this->send(NotificationEvent::AppointmentConfirmation, $appointment);
    }

    public function rescheduled(Appointment $appointment): void
    {
        $this->send(NotificationEvent::AppointmentRescheduled, $appointment);
    }

    public function cancelled(Appointment $appointment): void
    {
        $this->send(NotificationEvent::AppointmentCancelled, $appointment);
    }

    public function reminder(Appointment $appointment): void
    {
        $this->send(NotificationEvent::AppointmentReminder, $appointment);
    }

    private function send(NotificationEvent $event, Appointment $appointment): void
    {
        $appointment->loadMissing('location.tenant', 'service');

        $this->dispatcher->send(new SmsContext(
            event: $event,
            to: $appointment->customer_phone,
            consent: $appointment->sms_consent,
            locationId: $appointment->location_id,
            vars: [
                'first_name' => Str::before(trim($appointment->customer_name), ' '),
                'service' => $appointment->service->name,
                'location' => $appointment->location->name,
                'appointment_time' => $appointment->localStart()->isoFormat('ddd MMM D, h:mm A'),
                'confirmation_code' => $appointment->confirmation_code,
                'manage_link' => $appointment->manageUrl(),
                'checkin_link' => $appointment->checkinUrl(),
            ],
            appointmentId: $appointment->id,
            customerId: $appointment->customer_id,
            locale: (string) $appointment->location->tenant->settings()->get('locale', 'en'),
        ));
    }
}
