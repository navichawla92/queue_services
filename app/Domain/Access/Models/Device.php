<?php

namespace App\Domain\Access\Models;

use App\Domain\Access\Concerns\Auditable;
use App\Domain\Organization\Models\Location;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A paired lobby display or kiosk. Authenticates with a long-lived token
 * (stored as a SHA-256 hash); limited to its location and function.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $location_id
 * @property string $type
 * @property string $name
 * @property string|null $token_hash
 * @property array<string, mixed>|null $config
 * @property Carbon|null $revoked_at
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $paired_at
 */
class Device extends Model
{
    use Auditable, BelongsToTenant;

    public const TYPE_DISPLAY = 'display';

    public const TYPE_KIOSK = 'kiosk';

    public const TYPES = [self::TYPE_DISPLAY, self::TYPE_KIOSK];

    protected $fillable = ['location_id', 'type', 'name', 'config'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'paired_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
