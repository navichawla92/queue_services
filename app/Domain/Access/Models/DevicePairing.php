<?php

namespace App\Domain\Access\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Pending pairing request from an unpaired browser. Tenant-less until an
 * admin claims its code (then linked to a tenant-owned Device).
 *
 * @property string $code
 * @property string $type
 * @property string $claim_hash
 * @property int|null $device_id
 * @property string|null $token_encrypted
 * @property Carbon $expires_at
 */
class DevicePairing extends Model
{
    protected $fillable = ['code', 'type', 'claim_hash', 'expires_at'];

    protected $hidden = ['claim_hash', 'token_encrypted'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'token_encrypted' => 'encrypted',
        ];
    }

    /** @return BelongsTo<Device, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class)->withoutGlobalScopes();
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
