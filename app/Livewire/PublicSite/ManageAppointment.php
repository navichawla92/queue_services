<?php

namespace App\Livewire\PublicSite;

use App\Domain\Scheduling\AppointmentBooking;
use App\Domain\Scheduling\Exceptions\BookingNotAllowedException;
use App\Domain\Scheduling\Exceptions\SlotUnavailableException;
use App\Domain\Scheduling\Models\Appointment;
use App\Domain\Scheduling\SlotFinder;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** /a/{token}: view, reschedule or cancel an appointment (cutoff enforced). */
#[Layout('layouts.public')]
class ManageAppointment extends Component
{
    #[Locked]
    public int $appointmentId;

    public bool $rescheduling = false;

    public ?string $date = null;

    public ?string $message = null;

    public ?string $error = null;

    public function mount(string $appointment): void
    {
        $this->appointmentId = Appointment::query()->where('manage_token', $appointment)->firstOrFail()->id;
        $this->error = session('appointment_error');
    }

    public function cancel(AppointmentBooking $booking): void
    {
        try {
            $booking->cancel($this->appointment());
            $this->message = __('Your appointment has been cancelled.');
        } catch (BookingNotAllowedException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function startReschedule(): void
    {
        $this->rescheduling = true;
        $this->date = CarbonImmutable::now($this->appointment()->location->effectiveTimezone())->format('Y-m-d');
    }

    public function reschedule(string $slot, AppointmentBooking $booking): void
    {
        try {
            $booking->reschedule($this->appointment(), CarbonImmutable::createFromFormat('Y-m-d\TH:i', $slot, 'UTC'));
            $this->rescheduling = false;
            $this->message = __('Your appointment has been moved.');
        } catch (SlotUnavailableException|BookingNotAllowedException $e) {
            $this->error = $e->getMessage();
        }
    }

    private function appointment(): Appointment
    {
        return Appointment::query()->with('location.tenant', 'service', 'employee')->findOrFail($this->appointmentId);
    }

    public function render(SlotFinder $finder)
    {
        $appointment = $this->appointment();
        $tz = $appointment->location->effectiveTimezone();
        $slots = $this->rescheduling && $this->date
            ? $finder->slots($appointment->location, $appointment->service, CarbonImmutable::parse($this->date, $tz), ignoreAppointmentId: $appointment->id)
            : [];

        return view('livewire.public-site.manage-appointment', [
            'appointment' => $appointment,
            'tz' => $tz,
            'slots' => $slots,
            'canChange' => $appointment->status->isUpcoming()
                && now()->addMinutes($appointment->location->booking_cutoff_minutes)->lessThan($appointment->starts_at),
            'isToday' => $appointment->localStart()->isSameDay(CarbonImmutable::now($tz)),
        ])->title(__('Your appointment'));
    }
}
