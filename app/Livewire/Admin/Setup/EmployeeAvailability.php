<?php

namespace App\Livewire\Admin\Setup;

use App\Domain\Access\LocationAccess;
use App\Domain\Organization\Models\Employee;
use App\Domain\Organization\Models\Location;
use App\Domain\Scheduling\Models\EmployeeSchedule;
use App\Domain\Scheduling\Models\TimeOff;
use App\Domain\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Weekly working hours per location and time off for one employee
 * (appointment-scheduling "Employee availability"). Managers edit anyone
 * they manage; employees edit their own when the company allows it.
 */
#[Layout('layouts.app')]
class EmployeeAvailability extends Component
{
    #[Locked]
    public int $employeeId;

    /** @var list<array<string, int|string|null>> */
    public array $rows = [];

    public string $offStart = '';

    public string $offEnd = '';

    public string $offReason = '';

    public ?string $saved = null;

    public function mount(?Employee $employee = null): void
    {
        // /staff/my-availability has no parameter: Livewire injects an empty model.
        if ($employee === null || ! $employee->exists) {
            $employee = Employee::query()->where('user_id', auth()->id())->firstOrFail();
        }
        $this->employeeId = $employee->id;
        $this->authorizeEmployee();

        $this->rows = EmployeeSchedule::query()->where('employee_id', $employee->id)
            ->orderBy('location_id')->orderBy('weekday')->orderBy('starts_at')->get()
            ->map(fn (EmployeeSchedule $s) => [
                'location_id' => $s->location_id, 'weekday' => $s->weekday,
                'starts_at' => substr($s->starts_at, 0, 5), 'ends_at' => substr($s->ends_at, 0, 5),
            ])->all();
    }

    public function hydrate(): void
    {
        $this->authorizeEmployee();
    }

    public function addRow(): void
    {
        $this->rows[] = ['location_id' => $this->locations()->first()?->id, 'weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '17:00'];
    }

    public function removeRow(int $i): void
    {
        unset($this->rows[$i]);
        $this->rows = array_values($this->rows);
    }

    public function saveSchedule(): void
    {
        $locationIds = $this->locations()->pluck('id')->all();

        $this->withValidator(function (Validator $v) {
            $v->after(function (Validator $v) {
                foreach ($this->rows as $i => $row) {
                    if (isset($row['starts_at'], $row['ends_at']) && $row['ends_at'] <= $row['starts_at']) {
                        $v->errors()->add("rows.$i.ends_at", __('End must be after start.'));
                    }
                }
            });
        })->validate([
            'rows' => ['array', 'max:100'],
            'rows.*.location_id' => ['required', Rule::in($locationIds)],
            'rows.*.weekday' => ['required', 'integer', 'between:0,6'],
            'rows.*.starts_at' => ['required', 'date_format:H:i'],
            'rows.*.ends_at' => ['required', 'date_format:H:i'],
        ]);

        DB::transaction(function () {
            EmployeeSchedule::query()->where('employee_id', $this->employeeId)->get()->each->delete();
            foreach ($this->rows as $row) {
                EmployeeSchedule::create(['employee_id' => $this->employeeId] + $row);
            }
        });

        $this->saved = __('Working hours saved.');
    }

    public function addTimeOff(): void
    {
        $this->validate([
            'offStart' => ['required', 'date'],
            'offEnd' => ['required', 'date', 'after:offStart'],
            'offReason' => ['nullable', 'string', 'max:255'],
        ]);

        $tz = app(TenantContext::class)->require()->settings()->timezone();
        TimeOff::create([
            'employee_id' => $this->employeeId,
            'starts_at' => CarbonImmutable::parse($this->offStart, $tz)->utc(),
            'ends_at' => CarbonImmutable::parse($this->offEnd, $tz)->utc(),
            'reason' => $this->offReason ?: null,
        ]);
        $this->reset('offStart', 'offEnd', 'offReason');
    }

    public function removeTimeOff(int $id): void
    {
        TimeOff::query()->where('employee_id', $this->employeeId)->findOrFail($id)->delete();
    }

    /** @return Collection<int, Location> locations this employee works at */
    private function locations()
    {
        $employee = $this->employee();

        return $employee->user->all_locations
            ? Location::query()->active()->orderBy('name')->get()
            : $employee->user->locations()->where('is_active', true)->orderBy('name')->get();
    }

    private function employee(): Employee
    {
        return Employee::query()->with('user')->findOrFail($this->employeeId);
    }

    private function authorizeEmployee(): void
    {
        $user = auth()->user();
        $employee = $this->employee();

        if ($employee->user_id === $user->id) {
            $allowed = $user->can('setup.manage')
                || (bool) app(TenantContext::class)->require()->settings()->get('employees_edit_own_schedule', false);
            abort_unless($allowed, 403);

            return;
        }

        abort_unless($user->can('setup.manage'), 403);
        $access = app(LocationAccess::class);
        if (! $access->coversAllLocations($user)) {
            $shared = $employee->user->all_locations
                || $employee->user->locations()->whereIn('locations.id', $access->accessibleLocations($user)->pluck('id'))->exists();
            abort_unless($shared, 403);
        }
    }

    public function render()
    {
        $tz = app(TenantContext::class)->require()->settings()->timezone();

        return view('livewire.admin.setup.employee-availability', [
            'employee' => $this->employee(),
            'locations' => $this->locations(),
            'timeOff' => TimeOff::query()->where('employee_id', $this->employeeId)->where('ends_at', '>=', now())->orderBy('starts_at')->get(),
            'tz' => $tz,
            'weekdays' => [1 => __('Monday'), 2 => __('Tuesday'), 3 => __('Wednesday'), 4 => __('Thursday'), 5 => __('Friday'), 6 => __('Saturday'), 0 => __('Sunday')],
        ]);
    }
}
