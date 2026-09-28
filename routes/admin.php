<?php

use App\Livewire\Admin\AuditLogViewer;
use App\Livewire\Admin\BrandingSettings;
use App\Livewire\Admin\Devices;
use Illuminate\Support\Facades\Route;

/*
| Company admin & location manager surface (/admin). Group middleware:
| web, auth, tenant.user, tenant.member (bootstrap/app.php).
*/

Route::middleware('can:access-admin')->group(function () {
    Route::get('/', fn () => view('admin.home'))->name('home');

    Route::get('/branding', BrandingSettings::class)->middleware('can:tenant.manage')->name('branding');
    Route::get('/audit', AuditLogViewer::class)->middleware('can:audit.view')->name('audit');
    Route::get('/devices', Devices::class)->middleware('can:displays.manage')->name('devices');
});
