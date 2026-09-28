<?php

namespace App\Http\Middleware;

use App\Domain\Access\DeviceContext;
use App\Domain\Access\Models\Device;
use App\Domain\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Device guard: authenticates a paired kiosk/display by its bearer token (or
 * the device_token cookie), binds its tenant, and restricts it to its type.
 * Usage: `device:display` or `device:kiosk`.
 */
class AuthenticateDevice
{
    public const COOKIE = 'device_token';

    public function __construct(
        private readonly TenantContext $context,
        private readonly DeviceContext $devices,
    ) {}

    public function handle(Request $request, Closure $next, ?string $type = null): Response
    {
        $token = $request->bearerToken() ?: $request->cookie(self::COOKIE);

        $device = is_string($token) && $token !== ''
            ? Device::withoutTenantScope()->with('tenant.plan')->where('token_hash', Device::hashToken($token))->first()
            : null;

        if ($device === null || $device->isRevoked()) {
            // Page loads go back to the pairing shell; API/Livewire calls get 401.
            if (! $request->expectsJson() && ! $request->hasHeader('X-Livewire') && $request->isMethod('GET')) {
                return redirect()->route($type === Device::TYPE_KIOSK ? 'display.kiosk.shell' : 'display.shell');
            }

            return response()->json(['message' => 'Device not paired.', 'paired' => false], 401);
        }

        if ($type !== null && $device->type !== $type) {
            return response()->json(['message' => 'Wrong device type.'], 403);
        }

        // Bind the tenant first: tenant-owned relations resolve through it.
        $this->context->set($device->tenant);
        $device->load('location');

        if (! $device->tenant->isActive() || ! $device->location->is_active) {
            $this->context->clear();

            return response()->json(['message' => 'Service unavailable.', 'unavailable' => true], 503);
        }

        $request->attributes->set('device', $device);
        $this->devices->set($device);

        if ($device->last_seen_at === null || $device->last_seen_at->lt(now()->subMinute())) {
            $device->timestamps = false;
            $device->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
