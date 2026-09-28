<?php

namespace Database\Factories;

use App\Domain\Organization\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Requires a bound tenant context (tenant_id is filled by BelongsToTenant).
 *
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        return [
            'name' => fake()->city().' Office',
            'address' => fake()->address(),
            'phone' => '+12025550100',
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
