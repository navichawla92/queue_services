<?php

namespace Database\Factories;

use App\Domain\Tenancy\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public const ALL_FEATURES = [
        'appointments' => true,
        'signage' => true,
        'feedback' => true,
        'white_label' => true,
        'advanced_analytics' => true,
        'api_access' => true,
    ];

    public function definition(): array
    {
        return [
            'code' => 'plan-'.fake()->unique()->lexify('??????'),
            'name' => 'Test plan',
            'features' => self::ALL_FEATURES,
            'limits' => [],
            'is_internal' => false,
        ];
    }

    /** @param  array<string, bool>  $features */
    public function withFeatures(array $features): static
    {
        return $this->state(fn () => ['features' => $features]);
    }

    /** @param  array<string, int|null>  $limits */
    public function withLimits(array $limits): static
    {
        return $this->state(fn () => ['limits' => $limits]);
    }
}
