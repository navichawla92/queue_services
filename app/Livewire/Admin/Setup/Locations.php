<?php

namespace App\Livewire\Admin\Setup;

use App\Domain\Access\LocationAccess;
use App\Domain\Billing\LimitGuard;
use App\Domain\Organization\Models\Location;
use App\Domain\Tenancy\TenantContext;
use App\Livewire\Admin\Concerns\AuthorizesLocations;
use DateTimeZone;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Locations list. Creating and (de)activating locations is company-level
 * (locations.manage); editing details needs setup.manage for that location.
 */
#[Layout('layouts.app')]
class Locations extends Component
{
    use AuthorizesLocations;

    public ?int $editingId = null;

    public string $name = '';

    public ?string $address = null;

    public ?string $timezone = null;

    public ?string $phone = null;

    public int $walkin_cutoff_minutes = 15;

    public bool $showInactive = false;

    public function mount(): void
    {
        Gate::authorize('setup.manage');
    }

    public function create(): void
    {
        Gate::authorize('locations.manage');
        $this->resetForm();
        $this->editingId = 0;
    }

    public function edit(int $id): void
    {
        $location = Location::query()->findOrFail($id);
        $this->authorizeLocation($location);

        $this->editingId = $location->id;
        $this->name = $location->name;
        $this->address = $location->address;
        $this->timezone = $location->timezone;
        $this->phone = $location->phone;
        $this->walkin_cutoff_minutes = $location->walkin_cutoff_minutes;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:500'],
            'timezone' => ['nullable', Rule::in(DateTimeZone::listIdentifiers())],
            'phone' => ['nullable', 'string', 'max:32'],
            'walkin_cutoff_minutes' => ['required', 'integer', 'min:0', 'max:240'],
        ]);

        if ($this->editingId) {
            $location = Location::query()->findOrFail($this->editingId);
            $this->authorizeLocation($location);
            $location->update($data);
        } else {
            Gate::authorize('locations.manage');
            app(LimitGuard::class)->assertCanAdd('locations', 'name');
            Location::create($data);
        }

        $this->resetForm();
    }

    public function setActive(int $id, bool $active): void
    {
        Gate::authorize('locations.manage');
        $location = Location::query()->findOrFail($id);
        $this->authorizeLocation($location, 'locations.manage');
        if ($active && ! $location->is_active) {
            app(LimitGuard::class)->assertCanAdd('locations', 'name');
        }
        $location->update(['is_active' => $active]);
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'name', 'address', 'timezone', 'phone');
        $this->walkin_cutoff_minutes = 15;
        $this->resetValidation();
    }

    public function render(LocationAccess $access)
    {
        $query = Location::query()->orderBy('name');
        if (! $access->coversAllLocations($this->actor())) {
            $query->whereIn('id', $this->actor()->locations()->select('locations.id'));
        }
        if (! $this->showInactive) {
            $query->where('is_active', true);
        }

        return view('livewire.admin.setup.locations', [
            'locations' => $query->get(),
            'timezones' => DateTimeZone::listIdentifiers(),
            'tenantTimezone' => app(TenantContext::class)->require()->settings()->timezone(),
        ]);
    }
}
