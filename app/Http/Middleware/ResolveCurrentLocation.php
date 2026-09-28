<?php

namespace App\Http\Middleware;

use App\Domain\Access\CurrentLocation;
use App\Domain\Access\LocationAccess;
use App\Domain\Organization\Models\Location;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Picks the staff user's working location: the one chosen in the switcher
 * (session) if still accessible, otherwise their first accessible location.
 */
class ResolveCurrentLocation
{
    public const SESSION_KEY = 'current_location_id';

    public function __construct(
        private readonly LocationAccess $access,
        private readonly CurrentLocation $current,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $locations = $this->access->accessibleLocations($user)->get();

        $chosen = $locations->firstWhere('id', $request->session()->get(self::SESSION_KEY))
            ?? $locations->first();

        $this->current->set($chosen);
        $request->session()->put(self::SESSION_KEY, $chosen?->id);

        view()->share('currentLocation', $chosen);
        view()->share('switchableLocations', $locations);

        return $next($request);
    }

    public static function switchTo(Request $request, Location $location): void
    {
        $request->session()->put(self::SESSION_KEY, $location->id);
    }
}
