<?php

use App\Domain\Access\CurrentLocation;
use App\Domain\Queue\QueueSnapshot;
use App\Http\Controllers\Staff\SwitchLocationController;
use App\Livewire\Admin\FeedbackReview;
use App\Livewire\Admin\Setup\EmployeeAvailability;
use App\Livewire\Staff\AppointmentsConsole;
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
    Route::get('/appointments', AppointmentsConsole::class)->middleware(['can:appointments.manage', 'feature:appointments'])->name('appointments');
    Route::get('/my-availability', EmployeeAvailability::class)->name('availability');
    Route::get('/my-feedback', FeedbackReview::class)->middleware('feature:feedback')->name('feedback');
});
