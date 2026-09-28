<?php

namespace App\Domain\Queue\Broadcasting;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Fire-and-forget real-time publishing. A broadcast failure (Reverb down) is
 * reported but never fails the queue action: every client also polls.
 * Circuit breaker: after a failure, publishing is skipped for a short while
 * so queue actions don't each wait on a dead WebSocket server.
 */
class LiveUpdates
{
    public const PAUSE_SECONDS = 30;

    private const CACHE_KEY = 'live-updates:paused-until';

    public function publish(ShouldBroadcast $event): void
    {
        if ((int) Cache::get(self::CACHE_KEY, 0) > time()) {
            return;
        }

        try {
            broadcast($event);
        } catch (Throwable $e) {
            Cache::put(self::CACHE_KEY, time() + self::PAUSE_SECONDS, self::PAUSE_SECONDS);
            report($e);
        }
    }
}
