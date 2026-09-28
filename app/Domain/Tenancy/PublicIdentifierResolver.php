<?php

namespace App\Domain\Tenancy;

use App\Domain\Tenancy\Models\Tenant;
use Closure;

/**
 * Maps an unguessable public identifier from a URL (tenant public id,
 * location check-in id, ...) to its tenant and, optionally, the matched
 * subject (e.g. the Location). Domains register lookups by kind.
 */
class PublicIdentifierResolver
{
    /** @var array<string, Closure(string): (array{0: Tenant, 1: object|null}|null)> */
    private array $resolvers = [];

    public function __construct()
    {
        $this->register('tenant', function (string $publicId) {
            $tenant = Tenant::query()->with('plan')->where('public_id', $publicId)->first();

            return $tenant ? [$tenant, null] : null;
        });
    }

    /** @param  Closure(string): (array{0: Tenant, 1: object|null}|null)  $resolver */
    public function register(string $kind, Closure $resolver): void
    {
        $this->resolvers[$kind] = $resolver;
    }

    /** @return array{0: Tenant, 1: object|null}|null */
    public function resolve(string $kind, string $publicId): ?array
    {
        $resolver = $this->resolvers[$kind] ?? null;

        return $resolver ? $resolver($publicId) : null;
    }
}
