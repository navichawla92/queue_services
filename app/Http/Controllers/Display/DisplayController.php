<?php

namespace App\Http\Controllers\Display;

use App\Domain\Access\DeviceContext;
use App\Domain\Display\DisplaySnapshot;
use App\Domain\Display\SignageFeed;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/** The lobby TV app (paired display devices only). */
class DisplayController extends Controller
{
    public function app(DeviceContext $devices): View
    {
        $device = $devices->require();

        return view('display.app', [
            'device' => $device,
            'channelKey' => $device->channel_key,
        ]);
    }

    public function signage(DeviceContext $devices, SignageFeed $feed): JsonResponse
    {
        return response()->json($feed->manifest($devices->require()))->header('Cache-Control', 'no-store');
    }

    public function snapshot(DeviceContext $devices, DisplaySnapshot $snapshot): JsonResponse
    {
        return response()->json($snapshot->for($devices->require()))
            ->header('Cache-Control', 'no-store');
    }
}
