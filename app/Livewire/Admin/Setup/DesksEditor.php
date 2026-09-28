<?php

namespace App\Livewire\Admin\Setup;

use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Desk;
use Illuminate\Validation\Rule;

class DesksEditor extends LocationEditor
{
    public ?int $editingId = null;

    public string $label = '';

    public ?int $department_id = null;

    public int $sort_order = 0;

    public function edit(int $id): void
    {
        $desk = $this->find($id);
        $this->editingId = $desk->id;
        $this->label = $desk->label;
        $this->department_id = $desk->department_id;
        $this->sort_order = $desk->sort_order;
    }

    public function save(): void
    {
        $data = $this->validate([
            'label' => [
                'required', 'string', 'max:50',
                Rule::unique('desks', 'label')->where('location_id', $this->locationId)->ignore($this->editingId),
            ],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('location_id', $this->locationId)],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ]);

        if ($this->editingId) {
            $this->find($this->editingId)->update($data);
        } else {
            Desk::create($data + ['location_id' => $this->locationId]);
        }

        $this->cancel();
    }

    public function setActive(int $id, bool $active): void
    {
        $this->find($id)->update(['is_active' => $active]);
    }

    public function cancel(): void
    {
        $this->reset('editingId', 'label', 'department_id', 'sort_order');
        $this->resetValidation();
    }

    private function find(int $id): Desk
    {
        return Desk::query()->where('location_id', $this->locationId)->findOrFail($id);
    }

    public function render()
    {
        return view('livewire.admin.setup.desks-editor', [
            'desks' => Desk::query()->with('department')->where('location_id', $this->locationId)
                ->orderBy('sort_order')->orderBy('label')->get(),
            'departments' => Department::query()->where('location_id', $this->locationId)->active()->ordered()->get(),
        ]);
    }
}
