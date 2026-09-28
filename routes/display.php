<?php

use App\Domain\Access\Models\Device;
use App\Http\Controllers\Display\DeviceController;
use App\Http\Controllers\Display\DisplayController;
use App\Http\Middleware\AuthenticateDevice;
use App\Livewire\PublicSite\KioskCheckin;
use Illuminate\Support\Facades\Route;

/*
| Device surfaces, authenticated by a paired device token (not a staff login).
|
|   /display       lobby TV shell (pairing screen until paired)
|   /kiosk         kiosk shell (pairing screen until paired) → /kiosk/app
*/

Route::get('/display', [DeviceController::class, 'shell'])->defaults('type', 'display')->name('shell');
Route::get('/kiosk', [DeviceController::class, 'shell'])->defaults('type', 'kiosk')->name('kiosk.shell');

Route::middleware('throttle:20,1')->group(function () {
    Route::post('/devices/pairings', [DeviceController::class, 'requestPairing'])->name('pairing.request');
    Route::post('/devices/pairings/status', [DeviceController::class, 'pairingStatus'])->name('pairing.status');
});

Route::middleware('device')->get('/device/me', [DeviceController::class, 'me'])->name('device.me');

Route::middleware('device:kiosk')->get('/kiosk/app', KioskCheckin::class)->name('kiosk.app');

Route::middleware('device:display')->group(function () {
    Route::get('/display/app', [DisplayController::class, 'app'])->name('app');
    Route::get('/display/snapshot', [DisplayController::class, 'snapshot'])->name('snapshot');
    Route::get('/display/signage', [DisplayController::class, 'signage'])->name('signage');
});

// Local development only: start a device session from a known token (used
// for headless screenshots / manual testing). Never registered in production.
if (app()->isLocal()) {
    Route::get('/dev/device-session/{token}', function (string $token) {
        $device = Device::withoutTenantScope()->where('token_hash', Device::hashToken($token))->firstOrFail();

        return redirect()->route($device->type === 'kiosk' ? 'display.kiosk.app' : 'display.app')
            ->withCookie(cookie(AuthenticateDevice::COOKIE, $token, 60, httpOnly: true, sameSite: 'lax'));
    })->name('dev.device-session');
}
