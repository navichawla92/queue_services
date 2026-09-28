<?php

namespace App\Providers;

use App\Domain\Queue\Broadcasting\BroadcastQueueChanges;
use App\Domain\Queue\Events\QueueChanged;
use App\Domain\Queue\Models\Ticket;
use App\Domain\Queue\QueueHousekeeping;
use App\Domain\Queue\QueueLock;
use App\Domain\Routing\ServiceTimes;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\PublicIdentifierResolver;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class QueueServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Holds the pending-change list of the open transaction.
        $this->app->singleton(QueueLock::class);
    }

    public function boot(): void
    {
        Event::listen(QueueChanged::class, BroadcastQueueChanges::class);

        // /t/{ticket}: the customer's live status page, by unguessable token.
        $this->app->make(PublicIdentifierResolver::class)->register('ticket', function (string $token) {
            $ticket = Ticket::withoutTenantScope()->where('public_token', $token)->first();
            $tenant = $ticket ? Tenant::query()->with('plan')->find($ticket->tenant_id) : null;

            return $tenant ? [$tenant, $ticket] : null;
        });

        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);

            $schedule->call(fn () => app(TenantContext::class)->eachActive(fn () => app(ServiceTimes::class)->refresh()))
                ->name('routing:refresh-service-times')->hourly()->withoutOverlapping();

            $schedule->call(fn () => app(TenantContext::class)->eachActive(fn () => app(QueueHousekeeping::class)->autoNoShow()))
                ->name('queue:auto-no-show')->everyMinute()->withoutOverlapping();

            $schedule->call(fn () => app(TenantContext::class)->eachActive(fn () => app(QueueHousekeeping::class)->closeout()))
                ->name('queue:closeout')->everyFifteenMinutes()->withoutOverlapping();
        });
    }
}
