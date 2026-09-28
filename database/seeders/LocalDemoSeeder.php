<?php

namespace Database\Seeders;

use App\Domain\Access\Roles;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Desk;
use App\Domain\Organization\Models\Employee;
use App\Domain\Organization\Models\Location;
use App\Domain\Organization\Models\OpeningHour;
use App\Domain\Organization\Models\Service;
use App\Domain\Tenancy\Models\Plan;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Local development data only: an internal plan, one tenant with a staffed
 * location, a login per role (password: "password"), and a platform admin.
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
            ?? Tenant::create(['plan_id' => $plan->id, 'name' => 'Demo Company', 'slug' => 'demo', 'settings' => ['timezone' => 'America/New_York']]);

        app(TenantContext::class)->run($tenant, fn () => $this->seedTenant($tenant));

        $root = User::query()->firstOrNew(['email' => 'platform@example.com']);
        $root->forceFill(['name' => 'Platform Admin', 'password' => Hash::make('password'), 'is_platform_admin' => true])->save();
    }

    private function seedTenant(Tenant $tenant): void
    {
        $location = Location::query()->firstOrCreate(['name' => 'Main Office'], ['address' => '100 Main Street', 'phone' => '+12025550100']);

        $departments = collect([['General Services', 'G', '#2563eb'], ['Loans', 'L', '#16a34a'], ['New Accounts', 'N', '#9333ea']])
            ->mapWithKeys(fn ($d) => [$d[1] => Department::query()->firstOrCreate(
                ['location_id' => $location->id, 'prefix' => $d[1]], ['name' => $d[0], 'color' => $d[2]]
            )]);

        $services = collect([
            ['Account Opening', 20, true, true, 'N'],
            ['Deposits & Withdrawals', 5, true, false, 'G'],
            ['Card Replacement', 10, true, false, 'G'],
            ['Loan Consultation', 30, false, true, 'L'],
        ])->mapWithKeys(function ($s) use ($location, $departments) {
            $service = Service::query()->firstOrCreate(['name' => $s[0]], [
                'expected_minutes' => $s[1], 'allow_walk_in' => $s[2], 'allow_appointment' => $s[3],
            ]);
            $service->offerAt($location, $departments[$s[4]]);

            return [$s[0] => $service];
        });

        $desks = collect(['Desk 1', 'Desk 2', 'Desk 3', 'Room A'])
            ->mapWithKeys(fn ($label) => [$label => Desk::query()->firstOrCreate(['location_id' => $location->id, 'label' => $label])]);

        if (! OpeningHour::query()->where('location_id', $location->id)->exists()) {
            foreach (range(1, 5) as $weekday) {
                OpeningHour::create(['location_id' => $location->id, 'weekday' => $weekday, 'opens_at' => '09:00', 'closes_at' => '17:00']);
            }
            OpeningHour::create(['location_id' => $location->id, 'weekday' => 6, 'opens_at' => '09:00', 'closes_at' => '12:00']);
        }

        $staff = [
            ['admin@example.com', 'Ada Admin', 'Ada', Roles::COMPANY_ADMIN, null, [], []],
            ['manager@example.com', 'Max Manager', 'Max', Roles::LOCATION_MANAGER, 'Room A', ['L', 'N'], ['Loan Consultation', 'Account Opening']],
            ['reception@example.com', 'Rita Reception', 'Rita', Roles::RECEPTIONIST, null, [], []],
            ['employee@example.com', 'Eli Employee', 'Eli', Roles::EMPLOYEE, 'Desk 1', ['G', 'N'], ['Deposits & Withdrawals', 'Card Replacement', 'Account Opening']],
        ];

        foreach ($staff as [$email, $name, $display, $role, $desk, $deps, $skills]) {
            $user = User::query()->firstOrNew(['email' => $email]);
            $user->forceFill(['name' => $name, 'password' => Hash::make('password'), 'tenant_id' => $tenant->id])->save();
            $user->syncRoles([$role]);
            $user->locations()->syncWithoutDetaching([$location->id]);

            $employee = Employee::query()->firstOrCreate(['user_id' => $user->id], ['display_name' => $display]);
            $employee->update(['default_desk_id' => $desk ? $desks[$desk]->id : null]);
            $employee->departments()->sync(collect($deps)->map(fn ($p) => $departments[$p]->id));
            $employee->services()->sync(collect($skills)->map(fn ($s) => $services[$s]->id));
        }
    }
}
