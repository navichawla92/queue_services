<?php

namespace App\Http\Middleware;

use App\Domain\Billing\Features;
use App\Domain\Billing\PlanCatalog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-side plan feature gate (saas-plans-usage "Feature gating").
 * Usage: `feature:signage`. Public pages 404; staff/admin get an upgrade page.
 */
class EnsurePlanFeature
{
    public function __construct(private readonly Features $features) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if ($this->features->enabled($feature)) {
            return $next($request);
        }

        if ($request->user() === null) {
            abort(404);
        }

        $message = __(':feature is available on higher plans. Ask your administrator about upgrading.', [
            'feature' => __(PlanCatalog::FEATURES[$feature] ?? $feature),
        ]);

        return $request->expectsJson() || $request->hasHeader('X-Livewire')
            ? response()->json(['message' => $message, 'upgrade' => true], 403)
            : response()->view('errors.upgrade', ['message' => $message], 403);
    }
}
