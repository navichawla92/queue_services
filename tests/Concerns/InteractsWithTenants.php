<?php

namespace Tests\Concerns;

use App\Domain\Tenancy\Models\Plan;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\Broadcast;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Two-tenant isolation harness (design.md Decision 2 guardrail).
 *
 * Typical use:
 *   [$a, $b] = $this->twoTenants();
 *   $record = $this->inTenant($b, fn () => Thing::create([...]));
 *   $this->assertCrossTenantHidden($this->userIn($a), "/staff/things/{$record->id}");
 */
trait InteractsWithTenants
{
    /** @param  array<string, mixed>  $attributes */
    protected function createTenant(array $attributes = [], ?Plan $plan = null): Tenant
    {
        $factory = Tenant::factory();
        if ($plan) {
            $factory = $factory->for($plan);
        }

        return $factory->create($attributes)->load('plan');
    }

    /** @return array{0: Tenant, 1: Tenant} */
    protected function twoTenants(): array
    {
        return [
            $this->createTenant(['name' => 'Tenant A']),
            $this->createTenant(['name' => 'Tenant B']),
        ];
    }

    protected function tenantContext(): TenantContext
    {
        return app(TenantContext::class);
    }

    /** Bind the tenant for the rest of the test (like a request would). */
    protected function actingAsTenant(?Tenant $tenant): static
    {
        $this->tenantContext()->set($tenant);

        return $this;
    }

    /**
     * @template T
     *
     * @param  callable(Tenant): T  $callback
     * @return T
     */
    protected function inTenant(Tenant $tenant, callable $callback): mixed
    {
        return $this->tenantContext()->run($tenant, $callback);
    }

    /** @param  array<string, mixed>  $attributes */
    protected function userIn(Tenant $tenant, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->forceFill(['tenant_id' => $tenant->id])->save();

        return $user;
    }

    /** A request by $user for another tenant's resource must look like it does not exist. */
    protected function assertCrossTenantHidden(User $user, string $uri, string $method = 'get'): void
    {
        $this->tenantContext()->clear();
        $response = $this->actingAs($user)->{$method.'Json'}($uri);

        $this->assertContains(
            $response->getStatusCode(),
            [403, 404],
            "Cross-tenant {$method} {$uri} returned {$response->getStatusCode()}; expected 404 (or 403)."
        );
        $this->tenantContext()->clear();
    }

    /** A user (or device) of one tenant must not be authorized for another tenant's channel. */
    protected function assertChannelDenied(mixed $user, string $channel): void
    {
        $request = request();
        $request->setUserResolver(fn () => $user);
        $request->merge(['channel_name' => 'private-'.$channel, 'socket_id' => '1234.5678']);

        try {
            Broadcast::auth($request);
            $this->fail("Channel {$channel} was authorized for a user of another tenant.");
        } catch (AccessDeniedHttpException|BroadcastException) {
            $this->addToAssertionCount(1);
        }
    }
}
