<?php

namespace App\Domain\Access;

use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Contracts\Session\Session;

/**
 * A platform admin temporarily acting inside a tenant for support. Start and
 * end are audited against the tenant; while active a banner is shown and the
 * admin has full access within that tenant only.
 */
class SupportSession
{
    public const TENANT_KEY = 'support.tenant_id';

    public const REASON_KEY = 'support.reason';

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly TenantContext $tenants,
    ) {}

    public function start(Session $session, User $admin, Tenant $tenant, string $reason): void
    {
        abort_unless($admin->is_platform_admin && $admin->tenant_id === null, 403);

        $session->put(self::TENANT_KEY, $tenant->id);
        $session->put(self::REASON_KEY, $reason);

        $this->audit->log('platform.support_session_started', $tenant, meta: ['reason' => $reason], tenantId: $tenant->id);
    }

    public function end(Session $session): void
    {
        $tenantId = $session->pull(self::TENANT_KEY);
        $reason = $session->pull(self::REASON_KEY);

        if ($tenantId) {
            $this->audit->log('platform.support_session_ended', meta: ['reason' => $reason], tenantId: (int) $tenantId);
        }
    }

    /** Tenant id requested by the session, if the user may use it. */
    public function requestedTenantId(Session $session, User $user): ?int
    {
        if (! $user->is_platform_admin || $user->tenant_id !== null) {
            return null;
        }

        $id = $session->get(self::TENANT_KEY);

        return $id ? (int) $id : null;
    }

    /** True while the signed-in platform admin is inside the bound tenant. */
    public function isActive(?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user instanceof User
            && $user->is_platform_admin
            && $user->tenant_id === null
            && $this->tenants->check()
            && app()->bound('session.store')
            && (int) session(self::TENANT_KEY) === $this->tenants->id();
    }

    public function reason(): ?string
    {
        return session(self::REASON_KEY);
    }
}
