<?php

namespace App\Domain\Scheduling;

use App\Domain\Scheduling\Models\Appointment;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/** Scheduled appointment upkeep for the current tenant. */
class AppointmentHousekeeping
{
    /** A reminder fires within this window after its offset time (runs every 5 min). */
    public const REMINDER_WINDOW_MINUTES = 30;

    public function __construct(
        private readonly AppointmentNotifier $notifier,
        private readonly AppointmentBooking $booking,
        private readonly TenantContext $tenants,
    ) {}

    /** Idempotent: each (appointment, offset) is sent at most once. */
    public function sendReminders(): int
    {
        $offsets = (array) $this->tenants->require()->settings()->get('reminder_offsets', [1440, 120]);
        $sent = 0;

        foreach ($offsets as $offset) {
            $offset = (int) $offset;
            // Due: start − offset ≤ now < start − offset + window, and booked before the offset time.
            Appointment::query()->with(['location.tenant', 'service'])
                ->whereIn('status', [AppointmentStatus::Booked->value, AppointmentStatus::Confirmed->value])
                ->whereBetween('starts_at', [now()->addMinutes($offset - self::REMINDER_WINDOW_MINUTES), now()->addMinutes($offset)])
                ->whereRaw('created_at <= DATE_SUB(starts_at, INTERVAL ? MINUTE)', [$offset])
                ->whereNotExists(fn ($q) => $q->from('appointment_reminders')
                    ->whereColumn('appointment_reminders.appointment_id', 'appointments.id')
                    ->where('offset_minutes', $offset))
                ->each(function (Appointment $appointment) use ($offset, &$sent) {
                    $inserted = DB::table('appointment_reminders')->insertOrIgnore([
                        'appointment_id' => $appointment->id, 'offset_minutes' => $offset, 'sent_at' => now(),
                    ]);
                    if ($inserted) {
                        $this->notifier->reminder($appointment);
                        $sent++;
                    }
                });
        }

        return $sent;
    }

    /** Appointments not checked in within the grace period become No-Show. */
    public function autoNoShow(): int
    {
        $count = 0;
        Appointment::query()->with('location', 'customer')
            ->whereIn('status', [AppointmentStatus::Booked->value, AppointmentStatus::Confirmed->value])
            ->where('starts_at', '<', now())
            ->each(function (Appointment $appointment) use (&$count) {
                if ($appointment->starts_at->copy()->addMinutes($appointment->location->appointment_grace_minutes)->isPast()) {
                    $this->booking->markNoShow($appointment);
                    $count++;
                }
            });

        return $count;
    }
}
