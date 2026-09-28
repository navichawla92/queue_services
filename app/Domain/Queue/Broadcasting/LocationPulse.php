<?php

namespace App\Domain\Queue\Broadcasting;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * "Something changed" ping for customer status pages of a location (so
 * positions and estimates refresh). Carries only the queue version.
 */
class LocationPulse implements ShouldBroadcastNow
{
    public function __construct(
        private readonly string $locationPublicId,
        public readonly int $version,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('location.'.$this->locationPublicId.'.pulse');
    }

    public function broadcastAs(): string
    {
        return 'queue.pulse';
    }

    /** @return array<string, int> */
    public function broadcastWith(): array
    {
        return ['version' => $this->version];
    }
}
