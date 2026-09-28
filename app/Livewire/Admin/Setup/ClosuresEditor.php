<?php

namespace App\Livewire\Admin\Setup;

use App\Domain\Organization\Models\Closure;
use App\Domain\Organization\Models\Department;
use Illuminate\Validation\Rule;

/** Holidays and date-specific special hours. */
class ClosuresEditor extends LocationEditor
{
    public string $date = '';

    public ?int $department_id = null;

    public bool $specialHours = false;

    public string $opens_at = '10:00';

    public string $closes_at = '14:00';

    public string $reason = '';

    public function add(): void
    {
        $rules = [
            'date' => ['required', 'date_format:Y-m-d'],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('location_id', $this->locationId)],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
        if ($this->specialHours) {
            $rules['opens_at'] = ['required', 'date_format:H:i'];
            $rules['closes_at'] = ['required', 'date_format:H:i', 'after:opens_at'];
        }
        $this->validate($rules);

        $duplicate = Closure::query()->where('location_id', $this->locationId)
            ->where('department_id', $this->department_id)->whereDate('date', $this->date)->exists();
        if ($duplicate) {
            $this->addError('date', __('There is already an entry for this date.'));

            return;
        }

        Closure::create([
            'location_id' => $this->locationId,
            'department_id' => $this->department_id,
            'date' => $this->date,
            'opens_at' => $this->specialHours ? $this->opens_at : null,
            'closes_at' => $this->specialHours ? $this->closes_at : null,
            'reason' => $this->reason ?: null,
        ]);

        $this->reset('date', 'department_id', 'specialHours', 'reason');
        $this->resetValidation();
    }

    public function remove(int $id): void
    {
        Closure::query()->where('location_id', $this->locationId)->findOrFail($id)->delete();
    }

    public function render()
    {
        return view('livewire.admin.setup.closures-editor', [
            'closures' => Closure::query()->where('location_id', $this->locationId)
                ->whereDate('date', '>=', now($this->location()->effectiveTimezone())->toDateString())
                ->orderBy('date')->get(),
            'departments' => Department::query()->where('location_id', $this->locationId)->ordered()->get()->keyBy('id'),
        ]);
    }
}
