<?php

namespace App\Livewire\Admin\Setup;

use App\Domain\Access\Roles;
use App\Domain\Billing\LimitGuard;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Desk;
use App\Domain\Organization\Models\Employee;
use App\Domain\Organization\Models\Service;
use App\Domain\Tenancy\TenantContext;
use App\Livewire\Admin\Concerns\AuthorizesLocations;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Staff & employee profiles.
 *  - users.manage (company admin): accounts, roles, location assignment,
 *    deactivation, plus everything below.
 *  - setup.manage (location manager): display name, departments, skills and
 *    default desk of employees working at their locations; changes are
 *    limited to their own locations' departments/desks.
 */
#[Layout('layouts.app')]
class Employees extends Component
{
    use AuthorizesLocations;

    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $role = Roles::EMPLOYEE;

    public bool $all_locations = false;

    /** @var list<int|string> */
    public array $location_ids = [];

    public string $display_name = '';

    /** @var list<int|string> */
    public array $department_ids = [];

    /** @var list<int|string> */
    public array $service_ids = [];

    public ?int $default_desk_id = null;

    public string $search = '';

    public ?string $flash = null;

    public function mount(): void
    {
        Gate::authorize('setup.manage');
    }

    public function create(): void
    {
        Gate::authorize('users.manage');
        $this->resetForm();
        $this->editingId = 0;
    }

    public function edit(int $employeeId): void
    {
        $employee = $this->findManageable($employeeId);
        $user = $employee->user;

        $this->resetForm();
        $this->editingId = $employee->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->roles()->value('name') ?? Roles::EMPLOYEE;
        $this->all_locations = $user->all_locations;
        $this->location_ids = $user->locations()->pluck('locations.id')->all();
        $this->display_name = $employee->display_name;
        $this->department_ids = $employee->departments()->pluck('departments.id')->all();
        $this->service_ids = $employee->services()->pluck('services.id')->all();
        $this->default_desk_id = $employee->default_desk_id;
    }

    public function save(): void
    {
        $canManageUsers = $this->actor()->can('users.manage');
        $employee = $this->editingId ? $this->findManageable($this->editingId) : null;
        if ($employee === null) {
            Gate::authorize('users.manage'); // creating an account
        }
        $existingUser = $employee === null ? null : $employee->user;

        $accessibleIds = $this->accessibleLocations()->pluck('id')->all();
        // Where this person works: from the form (users.manage) or as stored.
        $worksEverywhere = $canManageUsers ? $this->all_locations : (bool) $existingUser?->all_locations;
        $locationIds = $canManageUsers
            ? $this->location_ids
            : ($existingUser === null ? [] : $existingUser->locations()->pluck('locations.id')->all());
        $workLocationIds = $worksEverywhere ? $accessibleIds : array_map('intval', $locationIds);

        $rules = [
            'display_name' => ['required', 'string', 'max:50'],
            'department_ids' => ['array'],
            'department_ids.*' => [Rule::exists('departments', 'id')->whereIn('location_id', array_intersect($workLocationIds, $accessibleIds))],
            'service_ids' => ['array'],
            'service_ids.*' => [Rule::exists('services', 'id')->where('tenant_id', app(TenantContext::class)->id())],
            'default_desk_id' => ['nullable', Rule::exists('desks', 'id')->whereIn('location_id', array_intersect($workLocationIds, $accessibleIds))],
        ];
        if ($canManageUsers) {
            $rules += [
                'name' => ['required', 'string', 'max:100'],
                'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($existingUser?->id)],
                'role' => ['required', Rule::in(array_keys(Roles::matrix()))],
                'all_locations' => ['boolean'],
                'location_ids' => ['array'],
                'location_ids.*' => [Rule::in($accessibleIds)],
            ];
        }
        $this->validate($rules, ['department_ids.*.exists' => __('Departments must belong to the employee\'s locations.')]);

        if ($employee && $employee->user_id === $this->actor()->id && $canManageUsers && $this->role !== $this->actor()->roles()->value('name')) {
            $this->addError('role', __('You cannot change your own role.'));

            return;
        }

        $isNew = $employee === null;
        if ($isNew) {
            app(LimitGuard::class)->assertCanAdd('staff_users', 'email');
        }

        $employee = DB::transaction(function () use ($employee, $existingUser, $canManageUsers, $accessibleIds): Employee {
            if ($canManageUsers) {
                $user = $existingUser ?? new User;
                $user->fill(['name' => $this->name, 'email' => Str::lower($this->email)]);
                if (! $user->exists) {
                    $user->password = Hash::make(Str::random(40));
                }
                $user->forceFill(['tenant_id' => app(TenantContext::class)->id(), 'all_locations' => $this->all_locations])->save();
                $user->syncRoles([$this->role]);
                $user->locations()->sync(array_map('intval', $this->location_ids));

                $employee ??= Employee::create(['user_id' => $user->id, 'display_name' => $this->display_name]);
            }

            $employee ?? abort(403);
            $employee->update(['display_name' => $this->display_name, 'default_desk_id' => $this->default_desk_id]);
            $employee->services()->sync(array_map('intval', $this->service_ids));

            // Only departments at locations this admin manages are touched.
            $manageable = Department::query()->whereIn('location_id', $accessibleIds)->pluck('id')->all();
            $keep = $employee->departments()->whereNotIn('departments.id', $manageable)->pluck('departments.id')->all();
            $employee->departments()->sync(array_merge($keep, array_map('intval', $this->department_ids)));

            return $employee;
        });

        if ($isNew) {
            Password::broker()->sendResetLink(['email' => $employee->user->email]);
            $this->flash = __('Invitation sent to :email.', ['email' => $employee->user->email]);
        }

        $this->resetForm();
    }

    public function setActive(int $employeeId, bool $active): void
    {
        Gate::authorize('users.manage');
        $employee = $this->findManageable($employeeId);
        abort_if($employee->user_id === $this->actor()->id, 422, __('You cannot deactivate yourself.'));

        if ($active && ! $employee->user->is_active) {
            app(LimitGuard::class)->assertCanAdd('staff_users', 'email');
        }
        $active ? $employee->user->reactivate() : $employee->user->deactivate();
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    private function findManageable(int $employeeId): Employee
    {
        $employee = Employee::query()->with('user')->findOrFail($employeeId);

        if (! $this->coversAllLocations()) {
            $shared = $employee->user->locations()->whereIn('locations.id', $this->accessibleLocations()->pluck('id'))->exists();
            abort_unless($shared || $employee->user->all_locations, 403);
        }

        return $employee;
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'name', 'email', 'role', 'all_locations', 'location_ids', 'display_name', 'department_ids', 'service_ids', 'default_desk_id');
        $this->resetValidation();
    }

    public function render()
    {
        $locations = $this->accessibleLocations();
        $locationIds = $locations->pluck('id');

        $employees = Employee::query()
            ->with(['user.locations', 'departments', 'services', 'defaultDesk'])
            ->whereHas('user', function ($q) use ($locationIds) {
                if (! $this->coversAllLocations()) {
                    $q->where(fn ($q) => $q->where('all_locations', true)
                        ->orWhereHas('locations', fn ($l) => $l->whereIn('locations.id', $locationIds)));
                }
                if ($this->search !== '') {
                    $q->where(fn ($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('email', 'like', "%{$this->search}%"));
                }
            })
            ->orderBy('display_name')->get();

        $formLocationIds = $this->all_locations ? $locationIds->all() : array_map('intval', $this->location_ids);
        if ($this->editingId && ! $this->actor()->can('users.manage')) {
            $formLocationIds = $locationIds->all();
        }

        return view('livewire.admin.setup.employees', [
            'employees' => $employees,
            'locations' => $locations,
            'roles' => array_keys(Roles::matrix()),
            'departments' => Department::query()->with('location')->active()->whereIn('location_id', $formLocationIds)->whereIn('location_id', $locationIds)->ordered()->get(),
            'desks' => Desk::query()->with('location')->active()->whereIn('location_id', $formLocationIds)->whereIn('location_id', $locationIds)->orderBy('label')->get(),
            'services' => Service::query()->active()->orderBy('name')->get(),
            'canManageUsers' => $this->actor()->can('users.manage'),
        ]);
    }
}
