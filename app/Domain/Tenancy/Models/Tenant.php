<?php

namespace App\Domain\Tenancy\Models;

use App\Domain\Access\Concerns\Auditable;
use App\Domain\Tenancy\TenantSettings;
use App\Models\User;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A company. The root owner of all business data.
 *
 * @property int $id
 * @property string $public_id
 * @property string $status
 * @property array<string, mixed>|null $settings
 */
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use Auditable, HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    /** Mirrors the column defaults so new instances are complete before refresh. */
    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
        'primary_color' => '#1d4ed8',
        'accent_color' => '#f59e0b',
    ];

    protected $fillable = [
        'plan_id', 'name', 'slug', 'display_name', 'logo_path',
        'primary_color', 'accent_color', 'public_text', 'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'suspended_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Tenant $tenant) {
            $tenant->public_id ??= strtolower((string) Str::ulid());
            $tenant->slug ??= Str::slug($tenant->name).'-'.Str::lower(Str::random(4));
            $tenant->status ??= self::STATUS_ACTIVE;
        });
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function suspend(): void
    {
        $this->forceFill(['status' => self::STATUS_SUSPENDED, 'suspended_at' => now()])->save();
    }

    public function reactivate(): void
    {
        $this->forceFill(['status' => self::STATUS_ACTIVE, 'suspended_at' => null])->save();
    }

    public function brandName(): string
    {
        return $this->display_name ?: $this->name;
    }

    public function isWhiteLabel(): bool
    {
        return (bool) $this->plan?->hasFeature('white_label');
    }

    public function settings(): TenantSettings
    {
        return new TenantSettings($this);
    }

    protected static function newFactory(): TenantFactory
    {
        return TenantFactory::new();
    }
}
