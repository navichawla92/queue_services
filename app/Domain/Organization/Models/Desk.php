<?php

namespace App\Domain\Organization\Models;

use App\Domain\Access\Concerns\Auditable;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\DeskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A desk or room; its label is announced on the lobby display ("Desk 3").
 *
 * @property int $id
 * @property int $location_id
 * @property int|null $department_id
 * @property string $label
 * @property bool $is_active
 */
class Desk extends Model
{
    /** @use HasFactory<DeskFactory> */
    use Auditable, BelongsToTenant, HasFactory;

    protected $attributes = ['is_active' => true, 'sort_order' => 0];

    protected $fillable = ['location_id', 'department_id', 'label', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @param  Builder<Desk>  $query
     * @return Builder<Desk>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    protected static function newFactory(): DeskFactory
    {
        return DeskFactory::new();
    }
}
