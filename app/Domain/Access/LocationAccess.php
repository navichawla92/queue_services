<?php

namespace App\Domain\Access;

use App\Domain\Organization\Models\Location;
use App\Domain\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Location scope of a user's role (access-control spec): company admins,
 * users flagged all_locations and platform admins in a support session reach
 * every location of the tenant; everyone else only their assigned locations.
 */
class LocationAccess
{
    public function __construct(
        private readonly TenantContext $tenants,
        private readonly SupportSession $support,
    ) {}

    public function coversAllLocations(User $user): bool
    {
        return $user->all_locations
            || $user->hasRole(Roles::COMPANY_ADMIN)
            || $this->support->isActive($user);
    }

    public function canAccess(User $user, Location $location): bool
    {
        $userTenantId = $user->tenant_id ?? ($this->support->isActive($user) ? $this->tenants->id() : null);

        if ($userTenantId === null || $location->tenant_id !== $userTenantId) {
            return false;
        }

        return $this->coversAllLocations($user)
            || $user->locations()->whereKey($location->id)->exists();
    }

    /** Permission check combined with location scope. */
    public function allows(User $user, string $permission, Location $location): bool
    {
        return $user->can($permission) && $this->canAccess($user, $location);
    }

    /** @return Builder<Location> active locations within the user's scope (current tenant) */
    public function accessibleLocations(User $user): Builder
    {
        $query = Location::query()->active()->orderBy('name');

        if (! $this->coversAllLocations($user)) {
            $query->whereIn('id', $user->locations()->select('locations.id'));
        }

        return $query;
    }
}
