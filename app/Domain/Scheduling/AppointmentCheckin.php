<?php

namespace App\Domain\Scheduling;

use App\Domain\Organization\Models\Location;
use App\Domain\Queue\Actor;
use App\Domain\Queue\CheckinChannel;
use App\Domain\Queue\CheckinRequest;
use App\Domain\Queue\CustomerType;
use App\Domain\Queue\Exceptions\DuplicateCheckinException;
use App\Domain\Queue\IssueTicket;
use App\Domain\Queue\Models\Ticket;
use App\Domain\Queue\PhoneNumbers;
use App\Domain\Scheduling\Exceptions\BookingNotAllowedException;
use App\Domain\Scheduling\Models\Appointment;
use Carbon\CarbonImmutable;

/**
 * Appointment arrival (customer-check-in "Appointment check-in"): find
 * today's appointment by phone or confirmation code, then issue a ticket
 * linked to it, keeping its service and employee; on-time arrivals get the
 * tenant's priority boost (customer-routing "Queue ordering").
 */
class AppointmentCheckin
{
    /** Arrivals from this many minutes before the start count as on time. */
    public const EARLY_MINUTES = 30;

    public function __construct(
        private readonly IssueTicket $issue,
        private readonly PhoneNumbers $phones,
    ) {}

    /** Today's upcoming appointment at the location for a phone number or confirmation code. */
    public function find(Location $location, string $input): ?Appointment
    {
        $input = trim($input);
        if ($input === '') {
            return null;
        }

        $tz = $location->effectiveTimezone();
        $today = CarbonImmutable::now($tz);
        $query = Appointment::query()->with(['location.tenant', 'service'])
            ->where('location_id', $location->id)
            ->whereIn('status', [AppointmentStatus::Booked->value, AppointmentStatus::Confirmed->value])
            ->whereBetween('starts_at', [$today->startOfDay()->utc(), $today->endOfDay()->utc()]);

        $code = strtoupper(preg_replace('/\s+/', '', $input) ?? '');
        if (preg_match('/^[A-Z0-9]{6}$/', $code) && ! ctype_digit($code)) {
            return (clone $query)->where('confirmation_code', $code)->first();
        }

        $phone = $this->phones->normalize($input, (string) $location->tenant->settings()->get('phone_region', 'US'));

        return $phone ? $query->where('customer_phone', $phone)->orderBy('starts_at')->first() : null;
    }

    public function checkIn(Appointment $appointment, CheckinChannel $channel, Actor $actor): Ticket
    {
        $appointment->loadMissing('location.tenant', 'service');

        if ($appointment->ticket_id && $appointment->status === AppointmentStatus::Arrived) {
            return Ticket::query()->findOrFail($appointment->ticket_id);
        }
        if (! $appointment->status->isUpcoming()) {
            throw new BookingNotAllowedException(__('This appointment can no longer be checked in.'));
        }

        $location = $appointment->location;
        $now = CarbonImmutable::now();
        if (! $appointment->starts_at->copy()->setTimezone($location->effectiveTimezone())->isSameDay($now->setTimezone($location->effectiveTimezone()))) {
            throw new BookingNotAllowedException(__('Your appointment is not today.'));
        }

        $onTime = $now->greaterThanOrEqualTo($appointment->starts_at->copy()->subMinutes(self::EARLY_MINUTES))
            && $now->lessThanOrEqualTo($appointment->starts_at->copy()->addMinutes($location->appointment_grace_minutes));
        $boost = $onTime ? (int) $location->tenant->settings()->get('appointment_priority_boost', 10) : 0;

        try {
            $ticket = ($this->issue)(new CheckinRequest(
                location: $location,
                service: $appointment->service,
                name: $appointment->customer_name,
                phone: $appointment->customer_phone,
                smsConsent: $appointment->sms_consent,
                channel: $channel,
                customerType: CustomerType::Appointment,
                extraPriority: $boost,
                appointmentId: $appointment->id,
                assignedEmployeeId: $appointment->employee_id,
            ), $actor);
        } catch (DuplicateCheckinException $e) {
            $ticket = $e->existing;
        }

        $appointment->forceFill([
            'status' => AppointmentStatus::Arrived,
            'ticket_id' => $ticket->id,
            'checked_in_at' => $now,
        ])->save();

        return $ticket;
    }
}
