<?php

namespace App\Domain\Tenancy;

use App\Domain\Tenancy\Exceptions\MissingTenantException;
use App\Domain\Tenancy\Models\Tenant;
use Spatie\Permission\PermissionRegistrar;

/**
 * Holds the tenant the current request / job / scheduled task runs for.
 * Bound as a singleton; every tenant-owned query and write is scoped by it.
 */
class TenantContext
{
    private ?Tenant $tenant = null;

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
        $this->syncPermissionTeam();
    }

    public function clear(): void
    {
        $this->set(null);
    }

    public function check(): bool
    {
        return $this->tenant !== null;
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->id;
    }

    /** @throws MissingTenantException */
    public function require(): Tenant
    {
        return $this->tenant ?? throw new MissingTenantException;
    }

    /**
     * Run a callback as the given tenant, restoring the previous context after.
     *
     * @template T
     *
     * @param  callable(Tenant): T  $callback
     * @return T
     */
    public function run(Tenant $tenant, callable $callback): mixed
    {
        $previous = $this->tenant;
        $this->set($tenant);

        try {
            return $callback($tenant);
        } finally {
            $this->set($previous);
        }
    }

    /** Role/permission assignments are per tenant (spatie teams). */
    private function syncPermissionTeam(): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant?->id);
    }

    /**
     * Run a callback once per active tenant (used by scheduled tasks).
     *
     * @param  callable(Tenant): mixed  $callback
     */
    public function eachActive(callable $callback): void
    {
        Tenant::query()->where('status', Tenant::STATUS_ACTIVE)->orderBy('id')
            ->each(fn (Tenant $tenant) => $this->run($tenant, $callback));
    }
}
