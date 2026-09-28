<?php

namespace App\Livewire\PublicSite;

use App\Domain\Organization\Models\Employee;
use App\Domain\Organization\Models\Location;
use App\Domain\Organization\Models\Service;
use App\Domain\Queue\PhoneNumbers;
use App\Domain\Scheduling\AppointmentBooking;
use App\Domain\Scheduling\BookingRequest;
use App\Domain\Scheduling\Exceptions\BookingNotAllowedException;
use App\Domain\Scheduling\Exceptions\SlotUnavailableException;
use App\Domain\Scheduling\Models\Appointment;
use App\Domain\Scheduling\SlotFinder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Online booking at /book/{location} (appointment-scheduling "Online
 * booking"): service → (employee) → date → time → details → confirmation.
 */
#[Layout('layouts.public')]
class BookingPage extends Component
{
    /** Days offered in the date picker (capped by the location's horizon). */
    public const DAYS_SHOWN = 21;

    #[Locked]
    public int $locationId;

    public string $step = 'service'; // service | employee | date | details | done

    public ?int $serviceId = null;

    public ?int $employeeId = null;

    public ?string $date = null;

    public ?string $slot = null; // UTC "Y-m-d\TH:i"

    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public bool $smsConsent = true;

    #[Locked]
    public ?int $appointmentId = null;

    public ?string $error = null;

    public function mount(string $location): void
    {
        $this->locationId = Location::query()->where('checkin_public_id', $location)->firstOrFail()->id;
    }

    public function chooseService(int $id): void
    {
        $this->serviceId = $this->services()->firstWhere('id', $id)->id ?? abort(404);
        $this->step = $this->location()->booking_choose_employee ? 'employee' : 'date';
    }

    public function chooseEmployee(?int $id): void
    {
        $this->employeeId = $id && $this->employees()->contains('id', $id) ? $id : null;
        $this->step = 'date';
    }

    public function chooseDate(string $date): void
    {
        $this->date = CarbonImmutable::createFromFormat('Y-m-d', $date)?->format('Y-m-d') ?? abort(404);
        $this->slot = null;
    }

    public function chooseSlot(string $slot): void
    {
        $this->slot = $slot;
        $this->error = null;
        $this->step = 'details';
    }

    public function back(): void
    {
        $this->error = null;
        $this->step = match ($this->step) {
            'details' => 'date',
            'date' => $this->location()->booking_choose_employee ? 'employee' : 'service',
            default => 'service',
        };
    }

    public function submit(AppointmentBooking $booking, PhoneNumbers $phones): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'smsConsent' => ['boolean'],
        ]);
        $location = $this->location();
        $e164 = $phones->normalize($this->phone, (string) $location->tenant->settings()->get('phone_region', 'US'));
        if ($e164 === null) {
            $this->addError('phone', __('Please enter a valid mobile number.'));

            return;
        }

        try {
            $appointment = $booking->book(new BookingRequest(
                location: $location,
                service: Service::query()->findOrFail($this->serviceId),
                start: CarbonImmutable::createFromFormat('Y-m-d\TH:i', (string) $this->slot, 'UTC'),
                name: trim($this->name),
                phone: $e164,
                email: $this->email ?: null,
                smsConsent: $this->smsConsent,
                employee: $this->employeeId ? Employee::query()->find($this->employeeId) : null,
            ));
        } catch (SlotUnavailableException $e) {
            $this->error = $e->getMessage();
            $this->slot = null;
            $this->step = 'date';

            return;
        } catch (BookingNotAllowedException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->appointmentId = $appointment->id;
        $this->step = 'done';
    }

    private function location(): Location
    {
        return Location::query()->findOrFail($this->locationId);
    }

    /** @return Collection<int, Service> */
    private function services(): Collection
    {
        return Service::query()->offeredAt($this->location(), customerFacing: true)->where('allow_appointment', true)->get();
    }

    /** @return Collection<int, Employee> */
    private function employees(): Collection
    {
        $service = Service::query()->find($this->serviceId);
        if ($service === null) {
            return collect();
        }

        $ids = collect(range(0, 6))->flatMap(fn ($d) => collect(app(SlotFinder::class)->slots($this->location(), $service, now()->addDays($d)))->flatMap->employeeIds)->unique();

        return Employee::query()->whereIn('id', $ids)->orderBy('display_name')->get();
    }

    public function render(SlotFinder $finder)
    {
        $location = $this->location();
        $tz = $location->effectiveTimezone();
        $service = $this->serviceId ? Service::query()->find($this->serviceId) : null;
        $employee = $this->employeeId ? Employee::query()->find($this->employeeId) : null;

        $days = [];
        $slots = [];
        if ($this->step === 'date' && $service) {
            $today = CarbonImmutable::now($tz)->startOfDay();
            for ($d = 0; $d < min(self::DAYS_SHOWN, $location->booking_horizon_days + 1); $d++) {
                $day = $today->addDays($d);
                if (count($finder->slots($location, $service, $day, $employee)) > 0) {
                    $days[] = $day;
                }
            }
            $this->date ??= isset($days[0]) ? $days[0]->format('Y-m-d') : null;
            if ($this->date) {
                $slots = $finder->slots($location, $service, CarbonImmutable::parse($this->date, $tz), $employee);
            }
        }

        return view('livewire.public-site.booking', [
            'location' => $location,
            'tz' => $tz,
            'bookingOpen' => $location->booking_enabled && $location->is_active,
            'services' => $this->step === 'service' ? $this->services() : collect(),
            'employees' => $this->step === 'employee' ? $this->employees() : collect(),
            'service' => $service,
            'employee' => $employee,
            'days' => $days,
            'slots' => $slots,
            'chosen' => $this->slot ? CarbonImmutable::createFromFormat('Y-m-d\TH:i', $this->slot, 'UTC')?->setTimezone($tz) : null,
            'appointment' => $this->appointmentId ? Appointment::query()->find($this->appointmentId) : null,
        ])->title(__('Book an appointment'));
    }
}
