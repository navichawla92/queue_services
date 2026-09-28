<?php

namespace App\Livewire\Staff;

use App\Domain\Access\CurrentLocation;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Employee;
use App\Domain\Organization\Models\Service;
use App\Domain\Organization\OperatingHours;
use App\Domain\Queue\Actor;
use App\Domain\Queue\CheckinChannel;
use App\Domain\Queue\CheckinRequest;
use App\Domain\Queue\CustomerType;
use App\Domain\Queue\Exceptions\DuplicateCheckinException;
use App\Domain\Queue\Exceptions\UnroutableServiceException;
use App\Domain\Queue\IssueTicket;
use App\Domain\Queue\PhoneNumbers;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Receptionist check-in (customer-check-in "Check-in channels" d): any
 * service offered here (including staff-only), optional department override
 * and direct assignment; bypasses the self-service routability check.
 */
#[Layout('layouts.app')]
class ReceptionistCheckin extends Component
{
    public string $name = '';

    public string $phone = '';

    public bool $smsConsent = false;

    public ?int $serviceId = null;

    public ?int $departmentId = null;

    public ?int $assignedEmployeeId = null;

    public ?string $result = null;

    public ?string $warning = null;

    public function mount(): void
    {
        Gate::authorize('checkin.create');
    }

    public function save(IssueTicket $issue, PhoneNumbers $phones, CurrentLocation $current): void
    {
        Gate::authorize('checkin.create');
        $location = $current->require();

        $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'smsConsent' => ['boolean'],
            'serviceId' => ['required', Rule::in(Service::query()->offeredAt($location)->pluck('id')->all())],
            'departmentId' => ['nullable', Rule::exists('departments', 'id')->where('location_id', $location->id)],
            'assignedEmployeeId' => ['nullable', 'integer'],
        ]);

        $e164 = $phones->normalize($this->phone, (string) $location->tenant->settings()->get('phone_region', 'US'));
        if (trim($this->phone) !== '' && $e164 === null) {
            $this->addError('phone', __('Please enter a valid mobile number.'));

            return;
        }

        try {
            $ticket = $issue(new CheckinRequest(
                location: $location,
                service: Service::query()->findOrFail($this->serviceId),
                name: trim($this->name),
                phone: $e164,
                smsConsent: $this->smsConsent,
                channel: CheckinChannel::Receptionist,
                customerType: CustomerType::WalkIn,
                department: $this->departmentId ? Department::query()->findOrFail($this->departmentId) : null,
                assignedEmployeeId: $this->assignedEmployeeId ?: null,
            ), Actor::current());
            $this->result = __('Checked in: ticket :number', ['number' => $ticket->number]);
        } catch (DuplicateCheckinException $e) {
            $this->result = __('This number already has an active ticket: :number', ['number' => $e->existing->number]);
        } catch (UnroutableServiceException) {
            $this->addError('serviceId', __('This service is not offered at this location.'));

            return;
        }

        $this->reset('name', 'phone', 'smsConsent', 'serviceId', 'departmentId', 'assignedEmployeeId');
    }

    public function render(CurrentLocation $current, OperatingHours $hours)
    {
        $location = $current->require();

        return view('livewire.staff.receptionist-checkin', [
            'location' => $location,
            'services' => Service::query()->offeredAt($location)->get(),
            'departments' => Department::query()->where('location_id', $location->id)->active()->ordered()->get(),
            'employees' => Employee::query()->where('current_location_id', $location->id)->orderBy('display_name')->get(),
            'closed' => ! $hours->acceptsWalkIns($location, now()),
        ]);
    }
}
