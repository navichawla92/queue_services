<?php

namespace App\Domain\Organization\Models;

use App\Domain\Access\Concerns\Auditable;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Models\User;
use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * An office. Deactivated (never deleted) so history stays reportable.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string|null $timezone
 * @property string $checkin_public_id
 * @property bool $is_active
 */
class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use Auditable, BelongsToTenant, HasFactory;

    protected $attributes = ['is_active' => true];

    protected $fillable = ['name', 'address', 'timezone', 'phone', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (Location $location) {
            $location->checkin_public_id ??= strtolower((string) Str::ulid());
        });
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * @param  Builder<Location>  $query
     * @return Builder<Location>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Effective time zone (location override, else tenant default). */
    public function timezone(): string
    {
        return $this->tenant->settings()->forLocation($this)->timezone();
    }

    protected static function newFactory(): LocationFactory
    {
        return LocationFactory::new();
    }
}
