<?php

namespace App\Domain\Organization\Models;

use App\Domain\Access\Concerns\Auditable;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A company-wide service. Offered per location via location_service, which
 * also holds that location's default department for the service.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int $expected_minutes
 * @property bool $allow_walk_in
 * @property bool $allow_appointment
 * @property bool $customer_selectable
 * @property bool $is_active
 */
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use Auditable, BelongsToTenant, HasFactory;

    protected $attributes = [
        'expected_minutes' => 10,
        'allow_walk_in' => true,
        'allow_appointment' => false,
        'customer_selectable' => true,
        'sort_order' => 0,
        'is_active' => true,
    ];

    protected $fillable = [
        'name', 'description', 'expected_minutes', 'allow_walk_in', 'allow_appointment',
        'customer_selectable', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'expected_minutes' => 'integer',
            'allow_walk_in' => 'boolean',
            'allow_appointment' => 'boolean',
            'customer_selectable' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsToMany<Location, $this> */
    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class)->withPivot('department_id');
    }

    /** Offer (or update) this service at a location with its default department. */
    public function offerAt(Location $location, Department $department): void
    {
        abort_unless($department->location_id === $location->id, 422, 'Department must belong to the location.');

        $this->locations()->syncWithoutDetaching([$location->id => ['department_id' => $department->id]]);
    }

    public function withdrawFrom(Location $location): void
    {
        $this->locations()->detach($location->id);
    }

    /**
     * @param  Builder<Service>  $query
     * @return Builder<Service>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Services offered at a location (optionally only those customers may pick).
     *
     * @param  Builder<Service>  $query
     * @return Builder<Service>
     */
    public function scopeOfferedAt(Builder $query, Location $location, bool $customerFacing = false): Builder
    {
        return $query->active()
            ->whereHas('locations', fn ($q) => $q->whereKey($location->id))
            ->when($customerFacing, fn ($q) => $q->where('customer_selectable', true))
            ->orderBy('sort_order')->orderBy('name');
    }

    protected static function newFactory(): ServiceFactory
    {
        return ServiceFactory::new();
    }
}
