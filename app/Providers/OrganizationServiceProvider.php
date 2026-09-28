<?php

namespace App\Providers;

use App\Domain\Organization\Models\Location;
use App\Domain\Organization\OperatingHours;
use App\Domain\Tenancy\PublicIdentifierResolver;
use Illuminate\Support\ServiceProvider;

class OrganizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OperatingHours::class);
    }

    public function boot(): void
    {
        // /c/{location}, /book/{location}: the location's unguessable check-in id.
        $this->app->make(PublicIdentifierResolver::class)->register('location', function (string $publicId) {
            $location = Location::withoutTenantScope()->with('tenant.plan')->where('checkin_public_id', $publicId)->first();

            return $location ? [$location->tenant, $location] : null;
        });
    }
}
