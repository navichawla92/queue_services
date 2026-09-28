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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * An office. Deactivated (never deleted) so history stays reportable.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string|null $address
 * @property string|null $timezone
 * @property string|null $phone
 * @property string $checkin_public_id
 * @property int $walkin_cutoff_minutes
 * @property bool $booking_enabled
 * @property bool $booking_choose_employee
 * @property int $booking_lead_minutes
 * @property int $booking_horizon_days
 * @property int $booking_buffer_minutes
 * @property int $booking_cutoff_minutes
 * @property int|null $appointment_capacity_per_hour
 * @property int $appointment_grace_minutes
 * @property bool $feedback_enabled
 * @property bool $is_active
 */
class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use Auditable, BelongsToTenant, HasFactory;

    protected $attributes = [
        'is_active' => true, 'walkin_cutoff_minutes' => 15,
        'booking_enabled' => true, 'booking_choose_employee' => false, 'booking_lead_minutes' => 120,
        'booking_horizon_days' => 60, 'booking_buffer_minutes' => 5, 'booking_cutoff_minutes' => 60,
        'appointment_grace_minutes' => 15, 'feedback_enabled' => true,
    ];

    protected $fillable = [
        'name', 'address', 'timezone', 'phone', 'walkin_cutoff_minutes', 'is_active',
        'booking_enabled', 'booking_choose_employee', 'booking_lead_minutes', 'booking_horizon_days',
        'booking_buffer_minutes', 'booking_cutoff_minutes', 'appointment_capacity_per_hour', 'appointment_grace_minutes',
        'feedback_enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean', 'walkin_cutoff_minutes' => 'integer',
            'booking_enabled' => 'boolean', 'booking_choose_employee' => 'boolean',
            'booking_lead_minutes' => 'integer', 'booking_horizon_days' => 'integer',
            'booking_buffer_minutes' => 'integer', 'booking_cutoff_minutes' => 'integer',
            'appointment_capacity_per_hour' => 'integer', 'appointment_grace_minutes' => 'integer',
            'feedback_enabled' => 'boolean',
        ];
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

    /** @return HasMany<Department, $this> */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    /** @return HasMany<Desk, $this> */
    public function desks(): HasMany
    {
        return $this->hasMany(Desk::class);
    }

    /** @return BelongsToMany<Service, $this> */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class)->withPivot('department_id');
    }

    /** @return HasMany<OpeningHour, $this> */
    public function openingHours(): HasMany
    {
        return $this->hasMany(OpeningHour::class);
    }

    /** @return HasMany<Closure, $this> */
    public function closures(): HasMany
    {
        return $this->hasMany(Closure::class);
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
    public function effectiveTimezone(): string
    {
        return $this->tenant->settings()->forLocation($this)->timezone();
    }

    /** Public mobile check-in URL; the QR variant is tagged so the channel is recorded as "qr". */
    public function checkinUrl(bool $qr = false): string
    {
        return route('public.checkin', ['location' => $this->checkin_public_id] + ($qr ? ['src' => 'qr'] : []));
    }

    protected static function newFactory(): LocationFactory
    {
        return LocationFactory::new();
    }
}
