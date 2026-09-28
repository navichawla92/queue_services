<?php

use App\Domain\Tenancy\Models\Tenant;
use App\Http\Controllers\Platform\SupportSessionController;
use Illuminate\Support\Facades\Route;

/*
| Platform admin surface (/platform): tenants, plans, usage, support sessions.
| Group middleware: web, auth, tenant.user, platform.admin (bootstrap/app.php).
| The only place tenant scoping may be bypassed (audited).
*/

Route::get('/', fn () => view('platform.home', [
    'tenants' => Tenant::query()->with('plan')->orderBy('name')->get(),
]))->name('home');

Route::post('/tenants/{tenant}/support', [SupportSessionController::class, 'store'])->name('support.start');
Route::delete('/support', [SupportSessionController::class, 'destroy'])->name('support.end');
