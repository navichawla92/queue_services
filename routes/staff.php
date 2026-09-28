<?php

use App\Domain\Access\CurrentLocation;
use App\Domain\Queue\QueueSnapshot;
use App\Http\Controllers\Staff\SwitchLocationController;
use App\Livewire\Staff\QueueDashboard;
use App\Livewire\Staff\ReceptionistCheckin;
use Illuminate\Support\Facades\Route;

/*
| Staff surface (/staff): live queue dashboard, receptionist check-in,
| appointments console. Group middleware: web, auth, tenant.user,
| tenant.member, location.current (bootstrap/app.php).
*/

Route::middleware('can:access-staff')->group(function () {
    Route::get('/', QueueDashboard::class)->name('home');

    // Full queue state for resync / API clients (design Decision 5).
    Route::get('/queue/snapshot', fn (CurrentLocation $current, QueueSnapshot $snapshot) => response()->json($snapshot->toArray($current->require())))
        ->name('queue.snapshot');

    Route::post('/location/{location}', SwitchLocationController::class)->name('location.switch');

    Route::get('/checkin', ReceptionistCheckin::class)->middleware('can:checkin.create')->name('checkin');
});
