<?php

use App\Http\Controllers\Admin\LocationQrController;
use App\Livewire\Admin\AuditLogViewer;
use App\Livewire\Admin\BrandingSettings;
use App\Livewire\Admin\Devices;
use App\Livewire\Admin\Setup\Employees;
use App\Livewire\Admin\Setup\Locations;
use App\Livewire\Admin\Setup\LocationSetup;
use App\Livewire\Admin\Setup\Services;
use App\Livewire\Admin\SmsLog;
use App\Livewire\Admin\SmsSettings;
use Illuminate\Support\Facades\Route;

/*
| Company admin & location manager surface (/admin). Group middleware:
| web, auth, tenant.user, tenant.member, location.current (bootstrap/app.php).
| Location-level checks (LocationAccess) happen inside each screen.
*/

Route::middleware('can:access-admin')->group(function () {
    Route::get('/', fn () => view('admin.home'))->name('home');

    Route::get('/branding', BrandingSettings::class)->middleware('can:tenant.manage')->name('branding');
    Route::get('/audit', AuditLogViewer::class)->middleware('can:audit.view')->name('audit');
    Route::get('/sms', SmsSettings::class)->middleware('can:notifications.manage')->name('sms');
    Route::get('/sms/log', SmsLog::class)->middleware('can:notifications.manage')->name('sms.log');
    Route::get('/devices', Devices::class)->middleware('can:displays.manage')->name('devices');

    Route::middleware('can:setup.manage')->group(function () {
        Route::get('/locations', Locations::class)->name('locations');
        Route::get('/locations/{location}', LocationSetup::class)->name('locations.show');
        Route::get('/locations/{location}/qr.svg', [LocationQrController::class, 'svg'])->name('locations.qr');
        Route::get('/locations/{location}/poster', [LocationQrController::class, 'poster'])->name('locations.poster');
        Route::get('/services', Services::class)->name('services');
        Route::get('/employees', Employees::class)->name('employees');
    });
});
