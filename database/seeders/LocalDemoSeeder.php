<?php

namespace Database\Seeders;

use App\Domain\Access\Roles;
use App\Domain\Tenancy\Models\Plan;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Local development data only: an internal plan, one tenant, and a login per
 * role (password: "password"), plus a platform admin.
 */
class LocalDemoSeeder extends Seeder
{
    public function run(): void
    {
        $plan = Plan::query()->firstOrCreate(['code' => 'internal'], [
            'name' => 'Internal (unlimited)',
            'features' => array_fill_keys(['appointments', 'signage', 'feedback', 'white_label', 'advanced_analytics', 'api_access'], true),
            'limits' => [],
            'is_internal' => true,
        ]);

        $tenant = Tenant::query()->where('slug', 'demo')->first()
            ?? Tenant::create(['plan_id' => $plan->id, 'name' => 'Demo Company', 'slug' => 'demo']);

        $users = [
            ['admin@example.com', 'Ada Admin', Roles::COMPANY_ADMIN],
            ['manager@example.com', 'Max Manager', Roles::LOCATION_MANAGER],
            ['reception@example.com', 'Rita Reception', Roles::RECEPTIONIST],
            ['employee@example.com', 'Eli Employee', Roles::EMPLOYEE],
        ];

        app(TenantContext::class)->run($tenant, function () use ($users, $tenant) {
            foreach ($users as [$email, $name, $role]) {
                $user = User::query()->firstOrNew(['email' => $email]);
                $user->forceFill(['name' => $name, 'password' => Hash::make('password'), 'tenant_id' => $tenant->id])->save();
                $user->syncRoles([$role]);
            }
        });

        $root = User::query()->firstOrNew(['email' => 'platform@example.com']);
        $root->forceFill(['name' => 'Platform Admin', 'password' => Hash::make('password'), 'is_platform_admin' => true])->save();
    }
}
