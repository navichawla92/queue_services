<?php

namespace App\Livewire\Admin\Setup;

use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Service;

/** Which catalog services this location offers, and their default department. */
class LocationServicesEditor extends LocationEditor
{
    /** @var array<int|string, int|string|null> service id => department id ('' = not offered) */
    public array $offered = [];

    public bool $saved = false;

    public function mount(int $locationId): void
    {
        parent::mount($locationId);

        $this->offered = $this->location()->services()->get()
            ->mapWithKeys(fn (Service $s) => [$s->id => (int) $s->getRelation('pivot')->getAttribute('department_id')])->all();
    }

    public function save(): void
    {
        $location = $this->location();
        $departmentIds = Department::query()->where('location_id', $location->id)->pluck('id')->all();

        $this->validate([
            'offered' => ['array'],
            'offered.*' => ['nullable', 'in:'.implode(',', array_merge([''], $departmentIds))],
        ], ['offered.*.in' => __('Pick a department of this location.')]);

        $services = Service::query()->whereIn('id', array_keys($this->offered))->get()->keyBy('id');

        foreach ($this->offered as $serviceId => $departmentId) {
            $service = $services->get((int) $serviceId);
            if ($service === null) {
                continue;
            }

            if ($departmentId === null || $departmentId === '') {
                $service->withdrawFrom($location);
            } else {
                $service->offerAt($location, Department::query()->findOrFail((int) $departmentId));
            }
        }

        $this->saved = true;
    }

    public function render()
    {
        return view('livewire.admin.setup.location-services-editor', [
            'services' => Service::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'departments' => Department::query()->where('location_id', $this->locationId)->active()->ordered()->get(),
        ]);
    }
}
