<?php

use App\Http\Controllers\Display\DeviceController;
use Illuminate\Support\Facades\Route;

/*
| Device surfaces, authenticated by a paired device token (not a staff login).
|
|   /display   lobby TV (pairing screen until paired)
|   /kiosk     lobby check-in kiosk
*/

Route::get('/display', [DeviceController::class, 'shell'])->defaults('type', 'display')->name('shell');
Route::get('/kiosk', [DeviceController::class, 'shell'])->defaults('type', 'kiosk')->name('kiosk.shell');

Route::middleware('throttle:20,1')->group(function () {
    Route::post('/devices/pairings', [DeviceController::class, 'requestPairing'])->name('pairing.request');
    Route::post('/devices/pairings/status', [DeviceController::class, 'pairingStatus'])->name('pairing.status');
});

Route::middleware('device')->get('/device/me', [DeviceController::class, 'me'])->name('device.me');
