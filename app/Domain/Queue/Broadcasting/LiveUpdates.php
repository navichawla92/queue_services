<?php

namespace App\Domain\Queue\Broadcasting;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Throwable;

/**
 * Fire-and-forget real-time publishing. A broadcast failure (Reverb down) is
 * reported but never fails the queue action: every client also polls.
 */
class LiveUpdates
{
    public function publish(ShouldBroadcast $event): void
    {
        try {
            broadcast($event);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
