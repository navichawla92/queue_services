<?php

namespace App\Domain\Access\Models;

use App\Domain\Tenancy\TenantScope;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Append-only audit entry. Not using BelongsToTenant because platform-level
 * entries have no tenant; reads are still tenant-scoped via TenantScope and
 * the audit logger always stamps the current tenant.
 *
 * @property int|null $tenant_id
 * @property string $action
 * @property array<string, mixed>|null $before
 * @property array<string, mixed>|null $after
 * @property array<string, mixed>|null $meta
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);

        static::updating(fn () => throw new LogicException('Audit entries are append-only.'));
        static::deleting(fn () => throw new LogicException('Audit entries are append-only.'));
    }
}
