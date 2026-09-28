<?php

namespace Database\Factories;

use App\Domain\Organization\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Requires a bound tenant context.
 *
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'expected_minutes' => 10,
        ];
    }
}
