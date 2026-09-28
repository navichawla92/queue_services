<?php

namespace App\Domain\Access;

use App\Domain\Access\Models\AuditLog;
use App\Domain\Organization\Models\Location;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes audit entries (access-control spec "Audit logging"). The actor is
 * the authenticated user unless given; the tenant is the current context
 * unless given explicitly (platform events).
 */
class AuditLogger
{
    public function __construct(
        private readonly TenantContext $tenants,
        private readonly CurrentLocation $currentLocation,
    ) {}

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     * @param  array<string, mixed>  $meta
     * @param  array{type: string, id?: int|null, name?: string|null}|null  $actor
     */
    public function log(
        string $action,
        ?Model $subject = null,
        ?array $before = null,
        ?array $after = null,
        array $meta = [],
        ?int $tenantId = null,
        ?int $locationId = null,
        ?array $actor = null,
    ): AuditLog {
        $actor ??= $this->resolveActor();

        $entry = new AuditLog([
            'tenant_id' => $tenantId ?? $this->tenantOf($subject) ?? $this->tenants->id(),
            'location_id' => $locationId ?? $this->locationOf($subject) ?? $this->currentLocation->get()?->id,
            'actor_type' => $actor['type'],
            'actor_id' => $actor['id'] ?? null,
            'actor_name' => $actor['name'] ?? null,
            'action' => $action,
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'before' => $before,
            'after' => $after,
            'meta' => $meta ?: null,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);
        $entry->save();

        return $entry;
    }

    /** @return array{type: string, id: int|null, name: string|null} */
    private function resolveActor(): array
    {
        /** @var (Authenticatable&User)|null $user */
        $user = auth()->user();

        return $user
            ? ['type' => 'user', 'id' => $user->id, 'name' => $user->name]
            : ['type' => 'system', 'id' => null, 'name' => null];
    }

    private function tenantOf(?Model $subject): ?int
    {
        return match (true) {
            $subject instanceof Tenant => $subject->id,
            $subject !== null && $subject->getAttribute('tenant_id') !== null => (int) $subject->getAttribute('tenant_id'),
            default => null,
        };
    }

    private function locationOf(?Model $subject): ?int
    {
        return match (true) {
            $subject instanceof Location => $subject->id,
            $subject !== null && $subject->getAttribute('location_id') !== null => (int) $subject->getAttribute('location_id'),
            default => null,
        };
    }
}
