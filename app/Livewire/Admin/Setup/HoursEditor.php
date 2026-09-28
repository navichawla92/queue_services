<?php

namespace App\Livewire\Admin\Setup;

use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\OpeningHour;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Weekly hours for the location, or narrower hours for one department.
 * Rows are replaced as a whole on save.
 */
class HoursEditor extends LocationEditor
{
    /** '' = location hours; otherwise a department id. */
    public string $scope = '';

    /** @var list<array<string, int|string|null>> client-editable; validated on save */
    public array $rows = [];

    public bool $saved = false;

    public function mount(int $locationId): void
    {
        parent::mount($locationId);
        $this->loadRows();
    }

    public function updatedScope(): void
    {
        $this->validateOnly('scope', ['scope' => $this->scopeRules()]);
        $this->loadRows();
        $this->saved = false;
    }

    public function addRow(): void
    {
        $this->rows[] = ['weekday' => 1, 'opens_at' => '09:00', 'closes_at' => '17:00'];
    }

    public function removeRow(int $index): void
    {
        unset($this->rows[$index]);
        $this->rows = array_values($this->rows);
    }

    public function save(): void
    {
        $this->withValidator(function (Validator $validator) {
            $validator->after(function (Validator $v) {
                foreach ($this->rows as $i => $row) {
                    if (isset($row['opens_at'], $row['closes_at']) && $row['closes_at'] <= $row['opens_at']) {
                        $v->errors()->add("rows.$i.closes_at", __('Closing time must be after opening time.'));
                    }
                }
            });
        })->validate([
            'scope' => $this->scopeRules(),
            'rows' => ['array', 'max:50'],
            'rows.*.weekday' => ['required', 'integer', 'between:0,6'],
            'rows.*.opens_at' => ['required', 'date_format:H:i'],
            'rows.*.closes_at' => ['required', 'date_format:H:i'],
        ]);

        $departmentId = $this->scope === '' ? null : (int) $this->scope;

        DB::transaction(function () use ($departmentId) {
            OpeningHour::query()->where('location_id', $this->locationId)->where('department_id', $departmentId)
                ->get()->each->delete();

            foreach ($this->rows as $row) {
                OpeningHour::create([
                    'location_id' => $this->locationId,
                    'department_id' => $departmentId,
                    'weekday' => (int) $row['weekday'],
                    'opens_at' => $row['opens_at'],
                    'closes_at' => $row['closes_at'],
                ]);
            }
        });

        $this->saved = true;
    }

    /** @return list<mixed> */
    private function scopeRules(): array
    {
        return ['nullable', Rule::in(array_merge([''], array_map('strval',
            Department::query()->where('location_id', $this->locationId)->pluck('id')->all())))];
    }

    private function loadRows(): void
    {
        $this->rows = OpeningHour::query()
            ->where('location_id', $this->locationId)
            ->where('department_id', $this->scope === '' ? null : (int) $this->scope)
            ->orderBy('weekday')->orderBy('opens_at')->get()
            ->map(fn (OpeningHour $h) => [
                'weekday' => $h->weekday,
                'opens_at' => substr($h->opens_at, 0, 5),
                'closes_at' => substr($h->closes_at, 0, 5),
            ])->values()->all();
    }

    public function render()
    {
        return view('livewire.admin.setup.hours-editor', [
            'departments' => Department::query()->where('location_id', $this->locationId)->ordered()->get(),
            'weekdays' => [1 => __('Monday'), 2 => __('Tuesday'), 3 => __('Wednesday'), 4 => __('Thursday'), 5 => __('Friday'), 6 => __('Saturday'), 0 => __('Sunday')],
        ]);
    }
}
