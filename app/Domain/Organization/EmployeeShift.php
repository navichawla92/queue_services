<?php

namespace App\Domain\Organization;

use App\Domain\Access\LocationAccess;
use App\Domain\Organization\Events\EmployeeStatusChanged;
use App\Domain\Organization\Models\Desk;
use App\Domain\Organization\Models\Employee;
use App\Domain\Organization\Models\Location;
use App\Domain\Queue\Models\Ticket;
use App\Domain\Queue\QueueLock;
use App\Domain\Queue\TicketStatus;
use Illuminate\Validation\ValidationException;

/**
 * Live employee status and desk (organization-setup "Employee availability
 * status", "Manage desks and rooms"). Starting work at a location picks the
 * chosen desk, else the current one there, else the employee's default desk.
 */
class EmployeeShift
{
    public function __construct(private readonly LocationAccess $access) {}

    public function setStatus(Employee $employee, EmployeeStatus $status, Location $location, ?Desk $desk = null): Employee
    {
        if ($status === EmployeeStatus::Offline) {
            $employee->forceFill(['current_location_id' => null, 'current_desk_id' => null]);
        } else {
            if (! $this->access->canAccess($employee->user, $location)) {
                throw ValidationException::withMessages(['location' => __('You are not assigned to this location.')]);
            }

            $employee->forceFill([
                'current_location_id' => $location->id,
                'current_desk_id' => $this->resolveDesk($employee, $location, $desk)?->id,
            ]);
        }

        $previous = $employee->status;
        $employee->forceFill(['status' => $status, 'status_changed_at' => now()])->save();

        if ($previous !== $status) {
            EmployeeStatusChanged::dispatch($employee, $previous, $status);
        }

        return $employee;
    }

    public function changeDesk(Employee $employee, Desk $desk): Employee
    {
        if ($employee->current_location_id === null) {
            throw ValidationException::withMessages(['desk' => __('Start your shift first.')]);
        }

        $location = Location::query()->findOrFail($employee->current_location_id);
        $deskId = $this->resolveDesk($employee, $location, $desk)?->id;
        $employee->forceFill(['current_desk_id' => $deskId])->save();

        // A customer already called to this employee is redirected to the new desk.
        $lock = app(QueueLock::class);
        $lock->run($location, function () use ($employee, $deskId, $location, $lock) {
            Ticket::query()->where('serving_employee_id', $employee->id)
                ->whereIn('status', [TicketStatus::Called->value, TicketStatus::InService->value])
                ->where(fn ($q) => $q->whereNull('desk_id')->orWhere('desk_id', '!=', $deskId))
                ->get()
                ->each(function (Ticket $ticket) use ($deskId, $location, $lock) {
                    $ticket->forceFill(['desk_id' => $deskId])->save();
                    $lock->changed($location->id, 'desk_changed', $ticket->id);
                });
        });

        return $employee;
    }

    private function resolveDesk(Employee $employee, Location $location, ?Desk $chosen): ?Desk
    {
        if ($chosen !== null) {
            if ($chosen->location_id !== $location->id || ! $chosen->is_active) {
                throw ValidationException::withMessages(['desk' => __('Choose an active desk at this location.')]);
            }

            return $chosen;
        }

        foreach ([$employee->current_location_id === $location->id ? $employee->current_desk_id : null, $employee->default_desk_id] as $id) {
            $desk = $id ? Desk::query()->active()->where('location_id', $location->id)->find($id) : null;
            if ($desk) {
                return $desk;
            }
        }

        return null;
    }
}
