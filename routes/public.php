<?php

use App\Http\Controllers\PublicSite\TwilioWebhookController;
use App\Livewire\PublicSite\MobileCheckin;
use App\Livewire\PublicSite\TicketStatusPage;
use Illuminate\Support\Facades\Route;

/*
| Unauthenticated customer surfaces. Tenant/location is resolved from an
| unguessable public identifier or token in the URL, never from user input.
|
|   /c/{location}      QR / mobile check-in
|   /t/{ticket}        live ticket status page
|   /book/{location}   online booking
|   /a/{token}         manage appointment (reschedule / cancel)
|   /f/{token}         feedback form
|   /webhooks/sms/*    provider callbacks (signature-verified)
*/

Route::middleware(['tenant.public:location', 'throttle:checkin'])->group(function () {
    Route::get('/c/{location}', MobileCheckin::class)->name('checkin');
});

Route::middleware('tenant.public:ticket')->group(function () {
    Route::get('/t/{ticket}', TicketStatusPage::class)->name('ticket');
});

// Provider callbacks (CSRF-exempt, signature-verified per tenant).
Route::middleware(['tenant.public:tenant', 'throttle:120,1'])->prefix('webhooks/sms/twilio/{tenant}')->name('webhooks.sms.')->group(function () {
    Route::post('/status', [TwilioWebhookController::class, 'status'])->name('status');
    Route::post('/inbound', [TwilioWebhookController::class, 'inbound'])->name('inbound');
});
