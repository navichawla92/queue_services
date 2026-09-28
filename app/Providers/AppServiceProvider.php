<?php

namespace App\Providers;

use App\Domain\Access\CurrentLocation;
use App\Domain\Access\LocationAccess;
use App\Domain\Access\Roles;
use App\Domain\Access\SupportSession;
use App\Domain\Organization\Models\Location;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureTenantMember;
use App\Http\Middleware\ResolveCurrentLocation;
use App\Http\Middleware\ResolveTenantFromUser;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LocationAccess::class);
        $this->app->scoped(CurrentLocation::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // A platform admin in an audited support session has full access
        // within the supported tenant (and only there; see SupportSession).
        Gate::before(fn (User $user) => app(SupportSession::class)->isActive($user) ? true : null);

        // Surface access (role assignments are per tenant via spatie teams).
        Gate::define('access-admin', fn (User $user) => $user->hasAnyRole(Roles::ADMIN_CONSOLE_ROLES));
        Gate::define('access-staff', fn (User $user) => $user->roles()->exists());
        Gate::define('location.access', fn (User $user, Location $location) => app(LocationAccess::class)->canAccess($user, $location));

        // Livewire update requests (/livewire/update) must re-run the page's
        // tenant/location middleware, or tenant-scoped queries fail closed.
        Livewire::addPersistentMiddleware([
            ResolveTenantFromUser::class,
            EnsureTenantMember::class,
            EnsurePlatformAdmin::class,
            ResolveCurrentLocation::class,
        ]);

        // Test-only tables used by the tenant isolation harness.
        if ($this->app->runningUnitTests()) {
            $this->loadMigrationsFrom(base_path('tests/Fixtures/migrations'));
        }
    }
}
