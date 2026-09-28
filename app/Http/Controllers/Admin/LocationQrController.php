<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Access\LocationAccess;
use App\Domain\Organization\CheckinQrCode;
use App\Domain\Organization\Models\Location;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class LocationQrController extends Controller
{
    public function __construct(
        private readonly CheckinQrCode $qr,
        private readonly LocationAccess $access,
    ) {}

    public function svg(Request $request, Location $location): Response
    {
        abort_unless($this->access->allows($request->user(), 'setup.manage', $location), 403);

        return response($this->qr->svg($location), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'inline; filename="checkin-'.$location->id.'.svg"',
        ]);
    }

    public function poster(Request $request, Location $location, TenantContext $tenants): View
    {
        abort_unless($this->access->allows($request->user(), 'setup.manage', $location), 403);

        return view('admin.locations.poster', [
            'location' => $location,
            'tenant' => $tenants->require(),
            'qrSvg' => $this->qr->svg($location, 600),
        ]);
    }
}
