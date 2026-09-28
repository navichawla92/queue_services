<?php

namespace App\Livewire\PublicSite;

use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Location;
use App\Domain\Organization\Models\Service;
use App\Domain\Organization\OperatingHours;
use App\Domain\Queue\Actor;
use App\Domain\Queue\CheckinChannel;
use App\Domain\Queue\CheckinRequest;
use App\Domain\Queue\CustomerType;
use App\Domain\Queue\Exceptions\DuplicateCheckinException;
use App\Domain\Queue\Exceptions\UnroutableServiceException;
use App\Domain\Queue\IssueTicket;
use App\Domain\Queue\Models\Ticket;
use App\Domain\Queue\PhoneNumbers;
use App\Domain\Queue\QueuePositions;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Shared self-service check-in (customer-check-in spec): choose service →
 * enter name/phone/consent → ticket with position and estimate. Kiosk and
 * QR/mobile surfaces extend this with their own channel and location source.
 */
abstract class CheckinFlow extends Component
{
    public string $step = 'start'; // start | service | details | done | closed

    public ?int $serviceId = null;

    public ?int $departmentId = null;

    public string $name = '';

    public string $phone = '';

    public bool $smsConsent = false;

    #[Locked]
    public ?int $ticketId = null;

    public bool $existing = false;

    public ?string $error = null;

    public string $locale = 'en';

    abstract protected function location(): Location;

    abstract protected function channel(): CheckinChannel;

    abstract protected function actor(): Actor;

    public function bootCheckinFlow(): void
    {
        App::setLocale($this->locale);
    }

    public function setLocale(string $locale): void
    {
        if (in_array($locale, $this->languages(), true)) {
            $this->locale = $locale;
            App::setLocale($locale);
        }
    }

    public function begin(): void
    {
        $this->error = null;
        if (! app(OperatingHours::class)->acceptsWalkIns($this->location(), now())) {
            $this->step = 'closed';

            return;
        }
        $this->step = 'service';
    }

    public function chooseService(int $serviceId): void
    {
        $service = $this->services()->firstWhere('id', $serviceId);
        abort_if($service === null, 404);

        $this->serviceId = $service->id;
        $this->departmentId = null;
        $this->step = 'details';
    }

    public function submit(PhoneNumbers $phones, IssueTicket $issue): void
    {
        $settings = $this->location()->tenant->settings();
        $phoneRequired = (bool) $settings->get('checkin_phone_required', false);

        $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => [$phoneRequired ? 'required' : 'nullable', 'string', 'max:30'],
            'smsConsent' => ['boolean'],
            'departmentId' => ['nullable', 'integer'],
        ]);

        $e164 = $phones->normalize($this->phone, (string) $settings->get('phone_region', 'US'));
        if (trim($this->phone) !== '' && $e164 === null) {
            $this->addError('phone', __('Please enter a valid mobile number.'));

            return;
        }

        $service = $this->services()->firstWhere('id', $this->serviceId) ?? abort(404);
        $department = $this->departmentId && $this->canChooseDepartment()
            ? $this->departments()->firstWhere('id', $this->departmentId)
            : null;

        try {
            $ticket = $issue(new CheckinRequest(
                location: $this->location(),
                service: $service,
                name: trim($this->name),
                phone: $e164,
                smsConsent: $this->smsConsent,
                channel: $this->channel(),
                customerType: CustomerType::WalkIn,
                department: $department,
            ), $this->actor());
            $this->existing = false;
        } catch (DuplicateCheckinException $e) {
            $ticket = $e->existing;
            $this->existing = true;
        } catch (UnroutableServiceException $e) {
            $this->error = $e->getMessage();
            $this->step = 'service';

            return;
        }

        $this->ticketId = $ticket->id;
        $this->step = 'done';
        $this->afterCheckin($ticket);
    }

    /** Hook for surfaces (e.g. mobile redirects to the status page). */
    protected function afterCheckin(Ticket $ticket): void {}

    public function back(): void
    {
        $this->error = null;
        $this->step = match ($this->step) {
            'details' => 'service',
            default => 'start',
        };
    }

    public function startOver(): void
    {
        $this->reset('step', 'serviceId', 'departmentId', 'name', 'phone', 'smsConsent', 'ticketId', 'existing', 'error');
        $this->resetValidation();
    }

    /** @return Collection<int, Service> services customers may choose here for walk-ins */
    protected function services(): Collection
    {
        return Service::query()->offeredAt($this->location(), customerFacing: true)->where('allow_walk_in', true)->get();
    }

    /** @return Collection<int, Department> */
    protected function departments(): Collection
    {
        return Department::query()->where('location_id', $this->location()->id)->active()->ordered()->get();
    }

    protected function canChooseDepartment(): bool
    {
        return (bool) $this->location()->tenant->settings()->get('checkin_choose_department', false);
    }

    /** @return list<string> */
    protected function languages(): array
    {
        return (array) $this->location()->tenant->settings()->get('languages', ['en', 'es']);
    }

    /** @return array<string, mixed> shared view data */
    protected function viewData(): array
    {
        $ticket = $this->ticketId ? Ticket::query()->with('department', 'service')->find($this->ticketId) : null;
        $location = $this->location();
        $hours = app(OperatingHours::class);

        return [
            'location' => $location,
            'tenant' => app(TenantContext::class)->require(),
            'services' => $this->step === 'service' ? $this->services() : collect(),
            'selectedService' => $this->serviceId ? Service::query()->find($this->serviceId) : null,
            'departments' => $this->step === 'details' && $this->canChooseDepartment() ? $this->departments() : collect(),
            'phoneRequired' => (bool) $location->tenant->settings()->get('checkin_phone_required', false),
            'ticket' => $ticket,
            'estimate' => $ticket ? app(QueuePositions::class)->estimate($ticket) : null,
            'nextOpening' => $this->step === 'closed' && $location->is_active ? $hours->nextOpening($location, now()) : null,
            'languages' => $this->languages(),
        ];
    }
}
