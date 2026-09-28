<?php

namespace App\Domain\Tenancy\Concerns;

use App\Domain\Tenancy\Exceptions\MissingTenantException;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use App\Domain\Tenancy\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Required on every model with a tenant_id column (enforced by
 * tests/Architecture/TenantModelsTest). Scopes reads to the current tenant,
 * fills tenant_id on create, and refuses writes without a tenant.
 *
 * @mixin Model
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model) {
            $tenantId = app(TenantContext::class)->id();

            if ($tenantId === null) {
                throw new MissingTenantException('Cannot create '.class_basename($model).' without a tenant context.');
            }

            if ($model->getAttribute('tenant_id') !== null && (int) $model->getAttribute('tenant_id') !== $tenantId) {
                throw new LogicException('Cannot create '.class_basename($model).' for a different tenant.');
            }

            $model->setAttribute('tenant_id', $tenantId);
        });

        static::updating(function (Model $model) {
            if ($model->isDirty('tenant_id')) {
                throw new LogicException('tenant_id cannot be changed.');
            }
        });
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Explicitly bypass tenant scoping (platform admin & maintenance code only).
     *
     * @return Builder<static>
     */
    public static function withoutTenantScope(): Builder
    {
        return static::query()->withoutGlobalScope(TenantScope::class);
    }
}
