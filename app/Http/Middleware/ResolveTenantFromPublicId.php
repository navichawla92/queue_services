<?php

namespace App\Http\Middleware;

use App\Domain\Tenancy\PublicIdentifierResolver;
use App\Domain\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the tenant for unauthenticated public pages from the unguessable
 * identifier in a route parameter. Usage: `tenant.public:location` reads the
 * {location} parameter with the "location" resolver.
 */
class ResolveTenantFromPublicId
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly PublicIdentifierResolver $resolver,
    ) {}

    public function handle(Request $request, Closure $next, string $kind = 'tenant'): Response
    {
        $publicId = $request->route($kind);

        $resolved = is_string($publicId) ? $this->resolver->resolve($kind, $publicId) : null;

        if ($resolved === null) {
            abort(404);
        }

        [$tenant, $subject] = $resolved;

        if (! $tenant->isActive()) {
            return response()->view('public.unavailable', ['tenant' => $tenant], 503);
        }

        $this->context->set($tenant);
        $request->attributes->set('public_subject', $subject);

        return $next($request);
    }
}
