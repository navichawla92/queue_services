<?php

namespace App\Domain\Display;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Ping to one paired lobby display: its queue, config, signage changed, or
 * it was revoked. The channel name ends in the device's secret channel key
 * (rotated on revoke); the display then reloads its snapshot.
 */
class DisplayChanged implements ShouldBroadcastNow
{
    public const QUEUE = 'queue';

    public const CONFIG = 'config';

    public const SIGNAGE = 'signage';

    public const REVOKED = 'revoked';

    public function __construct(
        private readonly string $channelKey,
        public readonly string $reason,
        public readonly int $version = 0,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('display.'.$this->channelKey);
    }

    public function broadcastAs(): string
    {
        return 'display.changed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['reason' => $this->reason, 'version' => $this->version];
    }
}
