<?php

namespace App\Http\Controllers\Staff;

use App\Domain\Organization\Models\Location;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveCurrentLocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SwitchLocationController extends Controller
{
    public function __invoke(Request $request, Location $location): RedirectResponse
    {
        Gate::authorize('location.access', $location);

        ResolveCurrentLocation::switchTo($request, $location);

        return back();
    }
}
