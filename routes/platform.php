<?php

use App\Http\Controllers\Platform\SupportSessionController;
use App\Livewire\Platform\TenantsConsole;
use Illuminate\Support\Facades\Route;

/*
| Platform admin surface (/platform): tenants, plans, usage, support sessions.
| Group middleware: web, auth, tenant.user, platform.admin (bootstrap/app.php).
| The only place tenant scoping may be bypassed (audited).
*/

Route::get('/', TenantsConsole::class)->name('home');

Route::post('/tenants/{tenant}/support', [SupportSessionController::class, 'store'])->name('support.start');
Route::delete('/support', [SupportSessionController::class, 'destroy'])->name('support.end');
