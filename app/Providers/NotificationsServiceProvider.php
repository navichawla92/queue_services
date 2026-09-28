<?php

namespace App\Providers;

use App\Domain\Notifications\NotificationDispatcher;
use App\Domain\Notifications\QueueNotifications;
use App\Domain\Notifications\SmsAllowance;
use App\Domain\Notifications\SmsConfig;
use App\Domain\Queue\Events\QueueChanged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SmsConfig::class);
        $this->app->singleton(SmsAllowance::class);
        $this->app->singleton(NotificationDispatcher::class);
    }

    public function boot(): void
    {
        Event::listen(QueueChanged::class, QueueNotifications::class);
    }
}
