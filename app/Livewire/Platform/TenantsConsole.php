<?php

namespace App\Livewire\Platform;

use App\Domain\Access\AuditLogger;
use App\Domain\Access\Roles;
use App\Domain\Billing\Usage;
use App\Domain\Tenancy\Models\Plan;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Platform administration console (saas-plans-usage "Platform
 * administration console"): tenants with plan, status and headline usage;
 * create with invite, suspend / reactivate, change plan. All audited.
 */
#[Layout('layouts.app')]
class TenantsConsole extends Component
{
    public bool $creating = false;

    public string $name = '';

    public ?int $planId = null;

    public string $adminName = '';

    public string $adminEmail = '';

    public string $timezone = 'America/New_York';

    public ?string $flash = null;

    public function create(AuditLogger $audit): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'planId' => ['required', Rule::exists('plans', 'id')],
            'adminName' => ['required', 'string', 'max:100'],
            'adminEmail' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'timezone' => ['required', 'timezone'],
        ]);

        $tenant = DB::transaction(function () {
            $tenant = Tenant::create(['plan_id' => $this->planId, 'name' => $this->name, 'settings' => ['timezone' => $this->timezone]]);

            app(TenantContext::class)->run($tenant, function () use ($tenant) {
                $admin = new User(['name' => $this->adminName, 'email' => Str::lower($this->adminEmail), 'password' => Hash::make(Str::random(40))]);
                $admin->forceFill(['tenant_id' => $tenant->id])->save();
                $admin->assignRole(Roles::COMPANY_ADMIN);
            });

            return $tenant;
        });

        Password::broker()->sendResetLink(['email' => Str::lower($this->adminEmail)]);
        $audit->log('platform.tenant_created', $tenant, after: ['plan_id' => $this->planId, 'admin' => $this->adminEmail], tenantId: $tenant->id);

        $this->flash = __('Tenant :name created; invitation sent to :email.', ['name' => $tenant->name, 'email' => $this->adminEmail]);
        $this->reset('creating', 'name', 'planId', 'adminName', 'adminEmail');
    }

    public function setStatus(int $tenantId, bool $active, AuditLogger $audit): void
    {
        $tenant = Tenant::query()->findOrFail($tenantId);
        $active ? $tenant->reactivate() : $tenant->suspend();
        $audit->log($active ? 'platform.tenant_reactivated' : 'platform.tenant_suspended', $tenant, tenantId: $tenant->id);
    }

    public function changePlan(int $tenantId, int $planId, AuditLogger $audit): void
    {
        $tenant = Tenant::query()->findOrFail($tenantId);
        $plan = Plan::query()->findOrFail($planId);
        $before = $tenant->plan_id;
        $tenant->forceFill(['plan_id' => $plan->id])->save();
        $audit->log('platform.plan_changed', $tenant, ['plan_id' => $before], ['plan_id' => $plan->id], tenantId: $tenant->id);
        $this->flash = __(':name moved to :plan.', ['name' => $tenant->name, 'plan' => $plan->name]);
    }

    public function render(TenantContext $context)
    {
        $tenants = Tenant::query()->with('plan')->orderBy('name')->get();

        // Headline usage per tenant, computed inside each tenant's context.
        $usage = $tenants->mapWithKeys(fn (Tenant $t) => [$t->id => $context->run($t, function () {
            $u = app(Usage::class);
            $resources = $u->resources();

            return [
                'locations' => $resources['locations'],
                'users' => $resources['staff_users'],
                'sms' => $u->value('sms_segments'),
                'tickets' => $u->value('tickets'),
            ];
        })]);

        return view('livewire.platform.tenants', [
            'tenants' => $tenants,
            'usage' => $usage,
            'plans' => Plan::query()->orderBy('is_internal', 'desc')->orderBy('name')->get(),
            'timezones' => \DateTimeZone::listIdentifiers(),
        ]);
    }
}
