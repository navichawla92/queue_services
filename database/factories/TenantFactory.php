<?php

namespace Database\Factories;

use App\Domain\Tenancy\Models\Plan;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'name' => fake()->company(),
        ];
    }

    public function suspended(): static
    {
        return $this->afterCreating(fn (Tenant $tenant) => $tenant->suspend());
    }
}
