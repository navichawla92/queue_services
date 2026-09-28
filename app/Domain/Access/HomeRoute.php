<?php

namespace App\Domain\Access;

use App\Domain\Tenancy\TenantContext;
use App\Models\User;

/** The default landing screen for a user's role. */
class HomeRoute
{
    public static function for(User $user): string
    {
        if ($user->tenant_id === null || $user->tenant === null) {
            return $user->is_platform_admin
                ? route('platform.home', absolute: false)
                : route('login', absolute: false);
        }

        // Called right after sign-in, before tenant middleware has run.
        $isAdmin = app(TenantContext::class)->run($user->tenant, function () use ($user) {
            $user->unsetRelation('roles');

            return $user->hasAnyRole(Roles::ADMIN_CONSOLE_ROLES);
        });

        return $isAdmin ? route('admin.home', absolute: false) : route('staff.home', absolute: false);
    }
}
