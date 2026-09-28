<?php

use App\Http\Controllers\Staff\SwitchLocationController;
use Illuminate\Support\Facades\Route;

/*
| Staff surface (/staff): live queue dashboard, receptionist check-in,
| appointments console. Group middleware: web, auth, tenant.user,
| tenant.member, location.current (bootstrap/app.php).
*/

Route::middleware('can:access-staff')->group(function () {
    Route::get('/', fn () => view('staff.home'))->name('home');

    Route::post('/location/{location}', SwitchLocationController::class)->name('location.switch');
});
