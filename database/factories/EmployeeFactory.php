<?php

namespace Database\Factories;

use App\Domain\Organization\Models\Employee;
use App\Domain\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Requires a bound tenant context; creates the staff user in that tenant.
 *
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'user_id' => function () {
                $user = User::factory()->create();
                $user->forceFill(['tenant_id' => app(TenantContext::class)->id()])->save();

                return $user->id;
            },
            'display_name' => fake()->firstName(),
        ];
    }
}
