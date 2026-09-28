<?php

namespace App\Domain\Tenancy\Models;

use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A subscription plan: feature flags plus numeric limits (null = unlimited).
 *
 * @property array<string, bool> $features
 * @property array<string, int|null> $limits
 */
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    protected $fillable = ['code', 'name', 'features', 'limits', 'is_internal'];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'limits' => 'array',
            'is_internal' => 'boolean',
        ];
    }

    /** @return HasMany<Tenant, $this> */
    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    public function hasFeature(string $feature): bool
    {
        return (bool) ($this->features[$feature] ?? false);
    }

    /** Null means unlimited. */
    public function limit(string $key): ?int
    {
        return $this->limits[$key] ?? null;
    }

    protected static function newFactory(): PlanFactory
    {
        return PlanFactory::new();
    }
}
