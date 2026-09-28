<?php

namespace App\Providers;

use App\Domain\Queue\Events\QueueChanged;
use App\Domain\Scheduling\AppointmentHousekeeping;
use App\Domain\Scheduling\AppointmentTicketSync;
use App\Domain\Scheduling\Models\Appointment;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\PublicIdentifierResolver;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class SchedulingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(QueueChanged::class, AppointmentTicketSync::class);

        // /a/{appointment}: manage link by unguessable token.
        $this->app->make(PublicIdentifierResolver::class)->register('appointment', function (string $token) {
            $appointment = Appointment::withoutTenantScope()->where('manage_token', $token)->first();
            $tenant = $appointment ? Tenant::query()->with('plan')->find($appointment->tenant_id) : null;

            return $tenant ? [$tenant, $appointment] : null;
        });

        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);
            $schedule->call(fn () => app(TenantContext::class)->eachActive(fn () => app(AppointmentHousekeeping::class)->sendReminders()))
                ->name('appointments:reminders')->everyFiveMinutes()->withoutOverlapping();
            $schedule->call(fn () => app(TenantContext::class)->eachActive(fn () => app(AppointmentHousekeeping::class)->autoNoShow()))
                ->name('appointments:auto-no-show')->everyFiveMinutes()->withoutOverlapping();
        });
    }
}
