<?php

namespace App\Livewire\Staff;

use App\Domain\Access\CurrentLocation;
use App\Domain\Organization\Models\Employee;
use App\Domain\Organization\Models\Location;
use App\Domain\Organization\Models\Service;
use App\Domain\Queue\Actor;
use App\Domain\Queue\CheckinChannel;
use App\Domain\Queue\PhoneNumbers;
use App\Domain\Scheduling\AppointmentBooking;
use App\Domain\Scheduling\AppointmentCheckin;
use App\Domain\Scheduling\BookingRequest;
use App\Domain\Scheduling\Exceptions\BookingNotAllowedException;
use App\Domain\Scheduling\Exceptions\SlotUnavailableException;
use App\Domain\Scheduling\Models\Appointment;
use App\Domain\Scheduling\SlotFinder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Staff appointment console (appointment-scheduling "Staff booking"):
 * day view, book / reschedule (with explicit override outside
 * availability), cancel, check in, no-show. Permission: appointments.manage.
 */
#[Layout('layouts.app')]
class AppointmentsConsole extends Component
{
    #[Url]
    public ?string $date = null;

    public ?int $editingId = null; // 0 = new

    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public bool $smsConsent = true;

    public ?int $serviceId = null;

    public ?int $employeeId = null;

    public string $time = '';   // local "Y-m-d\TH:i"

    public bool $needsOverride = false;

    public bool $override = false;

    public ?string $flash = null;

    public ?string $error = null;

    public function mount(): void
    {
        Gate::authorize('appointments.manage');
        $this->date ??= CarbonImmutable::now($this->location()->effectiveTimezone())->format('Y-m-d');
    }

    public function create(): void
    {
        $this->resetForm();
        $this->editingId = 0;
        $this->time = $this->date.'T09:00';
    }

    public function edit(int $id): void
    {
        $a = $this->find($id);
        $this->resetForm();
        $this->editingId = $a->id;
        $this->serviceId = $a->service_id;
        $this->employeeId = $a->employee_id;
        $this->time = $a->localStart()->format('Y-m-d\TH:i');
    }

    public function save(AppointmentBooking $booking, PhoneNumbers $phones): void
    {
        Gate::authorize('appointments.manage');
        $location = $this->location();
        $isNew = $this->editingId === 0;

        $this->validate(array_filter([
            'name' => $isNew ? ['required', 'string', 'max:100'] : null,
            'phone' => $isNew ? ['nullable', 'string', 'max:30'] : null,
            'email' => $isNew ? ['nullable', 'email'] : null,
            'serviceId' => ['required', Rule::in(Service::query()->offeredAt($location)->where('allow_appointment', true)->pluck('id')->all())],
            'employeeId' => ['nullable', 'integer'],
            'time' => ['required', 'date_format:Y-m-d\TH:i'],
        ]));

        $start = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $this->time, $location->effectiveTimezone());
        $employee = $this->employeeId ? Employee::query()->findOrFail($this->employeeId) : null;
        $service = Service::query()->findOrFail($this->serviceId);

        // Outside availability → show a warning and require explicit override.
        $available = app(SlotFinder::class)->find($location, $service, $start, $employee, false, $isNew ? null : $this->editingId) !== null;
        if (! $available && ! $this->override) {
            $this->needsOverride = true;
            $this->error = __('That time is outside availability or conflicts with another appointment. Tick "Book anyway" to override.');

            return;
        }

        try {
            if ($isNew) {
                $e164 = $phones->normalize($this->phone, (string) $location->tenant->settings()->get('phone_region', 'US'));
                if (trim($this->phone) !== '' && $e164 === null) {
                    $this->addError('phone', __('Please enter a valid mobile number.'));

                    return;
                }
                $booking->book(new BookingRequest($location, $service, $start, trim($this->name), $e164, $this->email ?: null,
                    $this->smsConsent, $employee, 'staff', ! $available));
                $this->flash = __('Appointment booked.');
            } else {
                $booking->reschedule($this->find($this->editingId), $start, $employee, byStaff: true, override: ! $available);
                $this->flash = __('Appointment moved.');
            }
        } catch (SlotUnavailableException|BookingNotAllowedException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->resetForm();
        $this->date = $start->format('Y-m-d');
    }

    public function cancel(int $id, AppointmentBooking $booking): void
    {
        $this->run(function () use ($id, $booking) {
            $booking->cancel($this->find($id), byStaff: true, reason: 'cancelled by staff');
        }, __('Appointment cancelled.'));
    }

    public function checkIn(int $id, AppointmentCheckin $checkin): void
    {
        $this->run(function () use ($id, $checkin) {
            $ticket = $checkin->checkIn($this->find($id), CheckinChannel::Receptionist, Actor::current());
            $this->flash = __('Checked in: ticket :number', ['number' => $ticket->number]);
        });
    }

    public function noShow(int $id, AppointmentBooking $booking): void
    {
        $this->run(function () use ($id, $booking) {
            $booking->markNoShow($this->find($id));
        }, __('Marked as no-show.'));
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
    }

    private function run(callable $action, ?string $message = null): void
    {
        Gate::authorize('appointments.manage');
        $this->reset('flash', 'error');
        try {
            $action();
            $this->flash ??= $message;
        } catch (BookingNotAllowedException $e) {
            $this->error = $e->getMessage();
        }
    }

    private function find(int $id): Appointment
    {
        return Appointment::query()->with('location.tenant', 'service')->where('location_id', $this->location()->id)->findOrFail($id);
    }

    private function location(): Location
    {
        return app(CurrentLocation::class)->require();
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'name', 'phone', 'email', 'smsConsent', 'serviceId', 'employeeId', 'time', 'needsOverride', 'override', 'error');
        $this->resetValidation();
    }

    public function render()
    {
        $location = $this->location();
        $tz = $location->effectiveTimezone();
        $day = CarbonImmutable::parse((string) $this->date, $tz);

        return view('livewire.staff.appointments-console', [
            'location' => $location,
            'tz' => $tz,
            'appointments' => Appointment::query()->with('service', 'employee')
                ->where('location_id', $location->id)
                ->whereBetween('starts_at', [$day->startOfDay()->utc(), $day->endOfDay()->utc()])
                ->orderBy('starts_at')->get(),
            'services' => Service::query()->offeredAt($location)->where('allow_appointment', true)->get(),
            'employees' => Employee::query()->whereHas('user', fn ($q) => $q->where('is_active', true)->where(fn ($q) => $q->where('all_locations', true)->orWhereHas('locations', fn ($l) => $l->whereKey($location->id))))->orderBy('display_name')->get(),
            'isToday' => $day->isSameDay(CarbonImmutable::now($tz)),
        ]);
    }
}
