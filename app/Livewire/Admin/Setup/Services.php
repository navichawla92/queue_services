<?php

namespace App\Livewire\Admin\Setup;

use App\Domain\Organization\Models\Service;
use App\Livewire\Admin\Concerns\AuthorizesLocations;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Company-wide service catalog. Definitions affect every location, so only
 * users covering all locations may edit them; location managers see the
 * catalog read-only and choose what their location offers under Locations.
 */
#[Layout('layouts.app')]
class Services extends Component
{
    use AuthorizesLocations;

    public ?int $editingId = null;

    public string $name = '';

    public ?string $description = null;

    public int $expected_minutes = 10;

    public bool $allow_walk_in = true;

    public bool $allow_appointment = false;

    public bool $customer_selectable = true;

    public int $sort_order = 0;

    public function mount(): void
    {
        Gate::authorize('setup.manage');
    }

    public function create(): void
    {
        $this->authorizeCatalog();
        $this->resetForm();
        $this->editingId = 0;
    }

    public function edit(int $id): void
    {
        $this->authorizeCatalog();
        $s = Service::query()->findOrFail($id);
        $this->editingId = $s->id;
        $this->fill($s->only(['name', 'description', 'expected_minutes', 'allow_walk_in', 'allow_appointment', 'customer_selectable', 'sort_order']));
    }

    public function save(): void
    {
        $this->authorizeCatalog();

        $data = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'expected_minutes' => ['required', 'integer', 'min:1', 'max:480'],
            'allow_walk_in' => ['boolean'],
            'allow_appointment' => ['boolean'],
            'customer_selectable' => ['boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ]);

        if (! $data['allow_walk_in'] && ! $data['allow_appointment']) {
            $this->addError('allow_walk_in', __('Allow walk-ins, appointments, or both.'));

            return;
        }

        $this->editingId
            ? Service::query()->findOrFail($this->editingId)->update($data)
            : Service::create($data);

        $this->resetForm();
    }

    public function setActive(int $id, bool $active): void
    {
        $this->authorizeCatalog();
        Service::query()->findOrFail($id)->update(['is_active' => $active]);
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    private function authorizeCatalog(): void
    {
        abort_unless($this->actor()->can('setup.manage') && $this->coversAllLocations(), 403);
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'name', 'description', 'expected_minutes', 'allow_walk_in', 'allow_appointment', 'customer_selectable', 'sort_order');
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.admin.setup.services', [
            'services' => Service::query()->withCount('locations')->orderBy('sort_order')->orderBy('name')->get(),
            'canEdit' => $this->coversAllLocations(),
        ]);
    }
}
