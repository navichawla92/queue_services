<?php

namespace Database\Factories;

use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Requires a bound tenant context.
 *
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        return [
            'location_id' => Location::factory(),
            'name' => fake()->unique()->randomElement(['General Services', 'Loans', 'New Accounts', 'Deposits', 'Support', 'Billing', 'Claims', 'Returns']),
            'prefix' => fake()->unique()->lexify('?'),
        ];
    }
}
