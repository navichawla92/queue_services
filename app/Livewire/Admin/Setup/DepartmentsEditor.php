<?php

namespace App\Livewire\Admin\Setup;

use App\Domain\Organization\Models\Department;
use Illuminate\Validation\Rule;

class DepartmentsEditor extends LocationEditor
{
    public ?int $editingId = null;

    public string $name = '';

    public string $prefix = '';

    public string $color = '#2563eb';

    public int $sort_order = 0;

    public function edit(int $id): void
    {
        $d = $this->find($id);
        $this->editingId = $d->id;
        $this->name = $d->name;
        $this->prefix = $d->prefix;
        $this->color = $d->color;
        $this->sort_order = $d->sort_order;
    }

    public function save(): void
    {
        $this->prefix = strtoupper(trim($this->prefix));

        $data = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'prefix' => [
                'required', 'regex:/^[A-Z]{1,3}$/',
                Rule::unique('departments', 'prefix')->where('location_id', $this->locationId)->ignore($this->editingId),
            ],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ], [
            'prefix.unique' => __('This prefix is already used at this location.'),
            'prefix.regex' => __('Use 1–3 letters.'),
        ]);

        if ($this->editingId) {
            $this->find($this->editingId)->update($data);
        } else {
            Department::create($data + ['location_id' => $this->locationId]);
        }

        $this->resetForm();
    }

    public function setActive(int $id, bool $active): void
    {
        $this->find($id)->update(['is_active' => $active]);
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    private function find(int $id): Department
    {
        return Department::query()->where('location_id', $this->locationId)->findOrFail($id);
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'name', 'prefix', 'sort_order');
        $this->color = '#2563eb';
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.admin.setup.departments-editor', [
            'departments' => Department::query()->where('location_id', $this->locationId)->ordered()->get(),
        ]);
    }
}
