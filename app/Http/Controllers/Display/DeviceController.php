<?php

namespace App\Http\Controllers\Display;

use App\Domain\Access\DevicePairingService;
use App\Domain\Access\Models\Device;
use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthenticateDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DeviceController extends Controller
{
    public function __construct(private readonly DevicePairingService $pairing) {}

    /** Shell page for /display and /kiosk: pairing screen until paired. */
    public function shell(string $type): View
    {
        return view('devices.shell', ['type' => $type]);
    }

    public function requestPairing(Request $request): JsonResponse
    {
        $data = $request->validate(['type' => ['required', Rule::in(Device::TYPES)]]);

        return response()->json($this->pairing->request($data['type']), 201);
    }

    public function pairingStatus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:8'],
            'claim' => ['required', 'string', 'max:100'],
        ]);

        $result = $this->pairing->collect($data['code'], $data['claim']);
        $response = response()->json($result);

        if (isset($result['token'])) {
            // Cookie as a fallback when localStorage is wiped by the TV browser.
            $response->withCookie(cookie(AuthenticateDevice::COOKIE, $result['token'], 60 * 24 * 365 * 5, httpOnly: true, sameSite: 'lax'));
        }

        return $response;
    }

    public function me(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');

        $response = response()->json([
            'paired' => true,
            'device' => ['id' => $device->id, 'type' => $device->type, 'name' => $device->name, 'config' => $device->config ?? []],
            'location' => ['id' => $device->location->id, 'name' => $device->location->name],
            'tenant' => [
                'name' => $device->tenant->brandName(),
                'primary_color' => $device->tenant->primary_color,
                'accent_color' => $device->tenant->accent_color,
            ],
        ]);

        // Re-issue the cookie from a localStorage bearer token so page loads
        // (/kiosk/app, /display/app) authenticate too.
        if ($token = $request->bearerToken()) {
            $response->withCookie(cookie(AuthenticateDevice::COOKIE, $token, 60 * 24 * 365 * 5, httpOnly: true, sameSite: 'lax'));
        }

        return $response;
    }
}
