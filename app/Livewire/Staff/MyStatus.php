<?php

namespace App\Livewire\Staff;

use App\Domain\Access\CurrentLocation;
use App\Domain\Organization\EmployeeShift;
use App\Domain\Organization\EmployeeStatus;
use App\Domain\Organization\Models\Desk;
use App\Domain\Organization\Models\Employee;
use Livewire\Component;

/** The signed-in employee's live status and desk at the current location. */
class MyStatus extends Component
{
    public ?int $deskId = null;

    public function mount(): void
    {
        $employee = $this->employee();
        $this->deskId = $employee === null ? null : ($employee->current_desk_id ?? $employee->default_desk_id);
    }

    public function setStatus(string $status, EmployeeShift $shift, CurrentLocation $current): void
    {
        $employee = $this->employee();
        abort_if($employee === null, 403);

        $status = EmployeeStatus::from($status);
        abort_if($status === EmployeeStatus::Busy, 422); // set by the queue when serving

        $desk = $this->deskId ? Desk::query()->find($this->deskId) : null;
        $shift->setStatus($employee, $status, $current->require(), $desk);
        $this->deskId = $employee->current_desk_id;
    }

    public function updatedDeskId(EmployeeShift $shift): void
    {
        $employee = $this->employee();
        if ($employee && $employee->current_location_id && $this->deskId) {
            $shift->changeDesk($employee, Desk::query()->findOrFail($this->deskId));
        }
    }

    private function employee(): ?Employee
    {
        return Employee::query()->where('user_id', auth()->id())->first();
    }

    public function render(CurrentLocation $current)
    {
        $location = $current->get();

        return view('livewire.staff.my-status', [
            'employee' => $this->employee()?->load('currentDesk', 'currentLocation'),
            'location' => $location,
            'desks' => $location ? Desk::query()->active()->where('location_id', $location->id)->orderBy('sort_order')->orderBy('label')->get() : collect(),
            'statuses' => [EmployeeStatus::Available, EmployeeStatus::OnBreak, EmployeeStatus::Offline],
        ]);
    }
}
