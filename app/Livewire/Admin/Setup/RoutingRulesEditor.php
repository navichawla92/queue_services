<?php

namespace App\Livewire\Admin\Setup;

use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Service;
use App\Domain\Queue\CustomerType;
use App\Domain\Routing\Models\RoutingRule;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Validation\Rule;

/** Ordered routing rules of a location; the first active match wins. */
class RoutingRulesEditor extends LocationEditor
{
    public ?int $editingId = null;

    public ?int $service_id = null;

    public ?string $customer_type = null;

    /** @var list<int|string> */
    public array $weekdays = [];

    public ?string $starts_at = null;

    public ?string $ends_at = null;

    public ?int $department_id = null;

    public int $priority = 0;

    public int $sort_order = 0;

    public function edit(int $id): void
    {
        $rule = $this->find($id);
        $this->editingId = $rule->id;
        $this->service_id = $rule->service_id;
        $this->customer_type = $rule->customer_type;
        $this->weekdays = $rule->weekdays ?? [];
        $this->starts_at = $rule->starts_at ? substr($rule->starts_at, 0, 5) : null;
        $this->ends_at = $rule->ends_at ? substr($rule->ends_at, 0, 5) : null;
        $this->department_id = $rule->department_id;
        $this->priority = $rule->priority;
        $this->sort_order = $rule->sort_order;
    }

    public function save(): void
    {
        $data = $this->validate([
            'service_id' => ['nullable', Rule::exists('services', 'id')->where('tenant_id', app(TenantContext::class)->id())],
            'customer_type' => ['nullable', Rule::in(array_column(CustomerType::cases(), 'value'))],
            'weekdays' => ['array'],
            'weekdays.*' => ['integer', 'between:0,6'],
            'starts_at' => ['nullable', 'date_format:H:i'],
            'ends_at' => ['nullable', 'date_format:H:i', 'required_with:starts_at', 'after:starts_at'],
            'department_id' => ['required', Rule::exists('departments', 'id')->where('location_id', $this->locationId)],
            'priority' => ['required', 'integer', 'between:-100,100'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ]);
        $data['weekdays'] = $data['weekdays'] === [] ? null : array_values(array_map('intval', $data['weekdays']));
        $data['customer_type'] = $data['customer_type'] ?: null;

        $this->editingId
            ? $this->find($this->editingId)->update($data)
            : RoutingRule::create($data + ['location_id' => $this->locationId]);

        $this->cancel();
    }

    public function setActive(int $id, bool $active): void
    {
        $this->find($id)->update(['is_active' => $active]);
    }

    public function delete(int $id): void
    {
        $this->find($id)->delete();
    }

    public function cancel(): void
    {
        $this->reset('editingId', 'service_id', 'customer_type', 'weekdays', 'starts_at', 'ends_at', 'department_id', 'priority', 'sort_order');
        $this->resetValidation();
    }

    private function find(int $id): RoutingRule
    {
        return RoutingRule::query()->where('location_id', $this->locationId)->findOrFail($id);
    }

    public function render()
    {
        return view('livewire.admin.setup.routing-rules-editor', [
            'rules' => RoutingRule::query()->with('service', 'department')->where('location_id', $this->locationId)
                ->orderBy('sort_order')->orderBy('id')->get(),
            'services' => Service::query()->active()->orderBy('name')->get(),
            'departments' => Department::query()->where('location_id', $this->locationId)->active()->ordered()->get(),
            'days' => [1 => __('Mon'), 2 => __('Tue'), 3 => __('Wed'), 4 => __('Thu'), 5 => __('Fri'), 6 => __('Sat'), 0 => __('Sun')],
        ]);
    }
}
