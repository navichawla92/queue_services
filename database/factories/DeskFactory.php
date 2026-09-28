<?php

namespace Database\Factories;

use App\Domain\Organization\Models\Desk;
use App\Domain\Organization\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Requires a bound tenant context.
 *
 * @extends Factory<Desk>
 */
class DeskFactory extends Factory
{
    protected $model = Desk::class;

    public function definition(): array
    {
        return [
            'location_id' => Location::factory(),
            'label' => 'Desk '.fake()->unique()->numberBetween(1, 999),
        ];
    }
}
