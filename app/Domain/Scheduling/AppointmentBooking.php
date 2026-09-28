<?php

namespace App\Domain\Scheduling;

use App\Domain\Organization\Models\Employee;
use App\Domain\Queue\Models\Customer;
use App\Domain\Scheduling\Exceptions\BookingNotAllowedException;
use App\Domain\Scheduling\Exceptions\SlotUnavailableException;
use App\Domain\Scheduling\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Book, reschedule and cancel appointments (appointment-scheduling spec).
 * Double-booking protection: the candidate employees' rows are locked
 * (FOR UPDATE, in id order) and the slot is re-checked inside the lock.
 */
class AppointmentBooking
{
    private const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function __construct(
        private readonly SlotFinder $slots,
        private readonly AppointmentNotifier $notifier,
    ) {}

    public function book(BookingRequest $request): Appointment
    {
        $this->assertOnlineAllowed($request);

        $appointment = DB::transaction(function () use ($request) {
            $employeeId = $this->claimEmployee($request, null);

            $customer = $this->matchCustomer($request);
            $start = CarbonImmutable::instance($request->start)->utc();

            return Appointment::create([
                'location_id' => $request->location->id,
                'service_id' => $request->service->id,
                'employee_id' => $employeeId,
                'customer_id' => $customer->id,
                'customer_name' => $request->name,
                'customer_phone' => $request->phone,
                'customer_email' => $request->email,
                'sms_consent' => $request->smsConsent && $request->phone !== null,
                'starts_at' => $start,
                'ends_at' => $start->addMinutes($this->slots->durationMinutes($request->location, $request->service)),
                'status' => AppointmentStatus::Booked,
                'source' => $request->source,
                'confirmation_code' => $this->uniqueCode(),
                'manage_token' => Str::random(40),
                'notes' => $request->notes,
                'created_by' => $request->source === 'staff' ? auth()->id() : null,
            ]);
        });

        DB::afterCommit(fn () => $this->notifier->confirmed($appointment));

        return $appointment;
    }

    public function reschedule(Appointment $appointment, \DateTimeInterface $newStart, ?Employee $employee = null, bool $byStaff = false, bool $override = false): Appointment
    {
        if (! $appointment->status->isUpcoming()) {
            throw new BookingNotAllowedException(__('This appointment can no longer be changed.'));
        }
        if (! $byStaff) {
            $this->assertBeforeCutoff($appointment);
        }

        $request = new BookingRequest(
            $appointment->location, $appointment->service, $newStart, $appointment->customer_name,
            $appointment->customer_phone, $appointment->customer_email, $appointment->sms_consent,
            $employee, $byStaff ? 'staff' : 'online', $override,
        );

        DB::transaction(function () use ($appointment, $request) {
            $employeeId = $this->claimEmployee($request, $appointment->id);
            $start = CarbonImmutable::instance($request->start)->utc();

            $appointment->forceFill([
                'employee_id' => $employeeId,
                'starts_at' => $start,
                'ends_at' => $start->addMinutes($this->slots->durationMinutes($appointment->location, $appointment->service)),
                'reschedule_count' => $appointment->reschedule_count + 1,
            ])->save();
        });

        DB::afterCommit(fn () => $this->notifier->rescheduled($appointment));

        return $appointment;
    }

    public function cancel(Appointment $appointment, bool $byStaff = false, ?string $reason = null): Appointment
    {
        if (! $appointment->status->isUpcoming()) {
            throw new BookingNotAllowedException(__('This appointment can no longer be cancelled.'));
        }
        if (! $byStaff) {
            $this->assertBeforeCutoff($appointment);
        }

        $appointment->forceFill([
            'status' => AppointmentStatus::Cancelled,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ])->save();

        $this->notifier->cancelled($appointment);

        return $appointment;
    }

    public function markNoShow(Appointment $appointment): Appointment
    {
        if (! in_array($appointment->status, [AppointmentStatus::Booked, AppointmentStatus::Confirmed, AppointmentStatus::Arrived], true)) {
            return $appointment;
        }

        $appointment->forceFill(['status' => AppointmentStatus::NoShow])->save();
        $appointment->customer?->increment('no_show_count');

        return $appointment;
    }

    /** Lock candidate employees and return the one who gets the slot. */
    private function claimEmployee(BookingRequest $request, ?int $ignoreAppointmentId): ?int
    {
        $staffOverride = $request->source === 'staff' && $request->override;
        $enforceWindow = $request->source !== 'staff';

        $slot = $this->slots->find($request->location, $request->service, $request->start, $request->employee, $enforceWindow, $ignoreAppointmentId);

        if ($slot === null) {
            if ($staffOverride) {
                return $request->employee?->id;
            }
            throw new SlotUnavailableException;
        }

        // Serialize concurrent bookings touching these employees.
        Employee::query()->whereIn('id', $slot->employeeIds)->orderBy('id')->lockForUpdate()->get();

        $recheck = $this->slots->find($request->location, $request->service, $request->start, $request->employee, $enforceWindow, $ignoreAppointmentId);
        if ($recheck === null) {
            if ($staffOverride) {
                return $request->employee?->id;
            }
            throw new SlotUnavailableException;
        }

        return $request->employee?->id ?? $this->leastBooked($recheck->employeeIds, $recheck->start);
    }

    /** @param  list<int>  $employeeIds */
    private function leastBooked(array $employeeIds, CarbonImmutable $start): int
    {
        $counts = Appointment::query()->occupying()->whereIn('employee_id', $employeeIds)
            ->whereBetween('starts_at', [$start->startOfDay()->utc(), $start->endOfDay()->utc()])
            ->selectRaw('employee_id, COUNT(*) as c')->groupBy('employee_id')->pluck('c', 'employee_id');

        usort($employeeIds, fn ($a, $b) => [($counts[$a] ?? 0), $a] <=> [($counts[$b] ?? 0), $b]);

        return $employeeIds[0];
    }

    private function assertOnlineAllowed(BookingRequest $request): void
    {
        if ($request->source === 'staff') {
            return;
        }
        if (! $request->location->booking_enabled || ! $request->location->is_active || ! $request->service->allow_appointment || ! $request->service->customer_selectable) {
            throw new BookingNotAllowedException(__('Online booking is not available for this service.'));
        }

        $limit = $request->location->tenant->settings()->get('booking_no_show_limit');
        if ($limit && $request->phone) {
            $noShows = (int) Customer::query()->where('phone', $request->phone)->value('no_show_count');
            if ($noShows >= (int) $limit) {
                throw new BookingNotAllowedException(__('Online booking is not available for this number. Please call us to book.'));
            }
        }
    }

    private function assertBeforeCutoff(Appointment $appointment): void
    {
        if (now()->addMinutes($appointment->location->booking_cutoff_minutes)->greaterThanOrEqualTo($appointment->starts_at)) {
            throw new BookingNotAllowedException(__('It is too close to your appointment to change it online. Please call the location instead.'));
        }
    }

    private function matchCustomer(BookingRequest $request): Customer
    {
        $customer = $request->phone ? Customer::query()->where('phone', $request->phone)->first() : null;
        $customer ??= new Customer(['phone' => $request->phone]);
        $customer->name = $request->name;
        $customer->email = $request->email ?: $customer->email;
        $customer->save();

        return $customer;
    }

    private function uniqueCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
        } while (Appointment::query()->where('confirmation_code', $code)->exists());

        return $code;
    }
}
