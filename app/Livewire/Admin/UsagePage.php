<?php

namespace App\Livewire\Admin;

use App\Domain\Billing\PlanCatalog;
use App\Domain\Billing\Usage;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Plan and usage for company admins (saas-plans-usage "Usage metering"). */
#[Layout('layouts.app')]
class UsagePage extends Component
{
    #[Url]
    public ?string $period = null;

    public function mount(Usage $usage): void
    {
        Gate::authorize('tenant.manage');
        $this->period ??= $usage->period();
    }

    public function render(Usage $usage)
    {
        $period = preg_match('/^\d{4}-\d{2}$/', (string) $this->period) ? (string) $this->period : $usage->period();
        $plan = app(TenantContext::class)->require()->plan;

        return view('livewire.admin.usage', [
            'plan' => $plan,
            'rows' => $usage->report($period),
            'features' => PlanCatalog::FEATURES,
            'periodValue' => $period,
        ]);
    }
}
