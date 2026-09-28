<?php

namespace App\Http\Middleware;

use App\Domain\Access\SupportSession;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the signed-in user's tenant (or, for a platform admin in a support
 * session, the supported tenant). Signs the user out if their account or
 * tenant is no longer active.
 */
class ResolveTenantFromUser
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly SupportSession $support,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $tenant = $user->tenant()->with('plan')->first();

        if (! $user->is_active || ($user->tenant_id !== null && ($tenant === null || ! $tenant->isActive()))) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $message = $user->is_active
                ? __('Your organization\'s account is currently unavailable.')
                : __('Your account has been deactivated.');

            return $request->expectsJson()
                ? response()->json(['message' => $message], 403)
                : redirect()->route('login')->withErrors(['email' => $message]);
        }

        if ($user->tenant_id === null && ($supportedId = $this->support->requestedTenantId($request->session(), $user))) {
            $tenant = Tenant::query()->with('plan')->find($supportedId);
        }

        $this->context->set($tenant);

        return $next($request);
    }
}
