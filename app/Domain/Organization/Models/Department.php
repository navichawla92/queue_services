<?php

namespace App\Domain\Organization\Models;

use App\Domain\Access\Concerns\Auditable;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A queue within a location. Its prefix starts ticket numbers ("A-012").
 *
 * @property int $id
 * @property int $location_id
 * @property string $name
 * @property string $prefix
 * @property string $color
 * @property int $sort_order
 * @property bool $is_active
 */
class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use Auditable, BelongsToTenant, HasFactory;

    protected $attributes = ['is_active' => true, 'color' => '#2563eb', 'sort_order' => 0];

    protected $fillable = ['location_id', 'name', 'prefix', 'color', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(fn (Department $d) => $d->prefix = strtoupper($d->prefix));
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return BelongsToMany<Employee, $this> */
    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class);
    }

    /**
     * @param  Builder<Department>  $query
     * @return Builder<Department>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<Department>  $query
     * @return Builder<Department>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    protected static function newFactory(): DepartmentFactory
    {
        return DepartmentFactory::new();
    }
}
