<?php

use App\Http\Middleware\AuthenticateDevice;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureTenantMember;
use App\Http\Middleware\ResolveCurrentLocation;
use App\Http\Middleware\ResolveTenantFromPublicId;
use App\Http\Middleware\ResolveTenantFromUser;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // One route file per surface; see design.md Decision 1.
            Route::middleware(['web', 'auth', 'tenant.user', 'tenant.member', 'location.current'])->prefix('admin')->name('admin.')
                ->group(base_path('routes/admin.php'));
            Route::middleware(['web', 'auth', 'tenant.user', 'tenant.member', 'location.current'])->prefix('staff')->name('staff.')
                ->group(base_path('routes/staff.php'));
            Route::middleware(['web', 'auth', 'tenant.user', 'platform.admin'])->prefix('platform')->name('platform.')
                ->group(base_path('routes/platform.php'));
            Route::middleware('web')->name('public.')
                ->group(base_path('routes/public.php'));
            Route::middleware('web')->name('display.')
                ->group(base_path('routes/display.php'));
        },
    )
    // Private channel auth runs as the signed-in staff user with their tenant bound.
    ->withBroadcasting(__DIR__.'/../routes/channels.php', ['middleware' => ['web', 'auth', 'tenant.user']])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant.user' => ResolveTenantFromUser::class,
            'tenant.public' => ResolveTenantFromPublicId::class,
            'tenant.member' => EnsureTenantMember::class,
            'platform.admin' => EnsurePlatformAdmin::class,
            'location.current' => ResolveCurrentLocation::class,
            'device' => AuthenticateDevice::class,
        ]);

        // The tenant must be bound before route-model binding runs, so bound
        // models are resolved through the tenant scope (cross-tenant ids 404).
        $middleware->prependToPriorityList(SubstituteBindings::class, ResolveTenantFromUser::class);
        $middleware->prependToPriorityList(SubstituteBindings::class, ResolveTenantFromPublicId::class);
        $middleware->prependToPriorityList(SubstituteBindings::class, AuthenticateDevice::class);

        // Provider callbacks are authenticated by their signature instead.
        $middleware->validateCsrfTokens(except: ['webhooks/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
