<?php

use App\Domain\Access\LocationAccess;
use App\Domain\Organization\Models\Location;
use App\Domain\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
| Private channel authorization. The auth route runs with web + auth +
| tenant.user (bootstrap/app.php), so the tenant context is bound; a
| channel of another tenant is always denied (tenant-isolation spec).
|
| Public channels (no auth, unguessable names, no PII):
|   ticket.{publicToken}             customer status page
|   location.{checkinPublicId}.pulse position refresh ping
|   display.{channelKey}             paired lobby display
*/

Broadcast::channel('tenant.{tenantId}.location.{locationId}.queue', function (User $user, int $tenantId, int $locationId) {
    if (app(TenantContext::class)->id() !== $tenantId) {
        return false;
    }

    $location = Location::query()->find($locationId);

    return $location !== null
        && $user->can('access-staff')
        && app(LocationAccess::class)->canAccess($user, $location);
});
