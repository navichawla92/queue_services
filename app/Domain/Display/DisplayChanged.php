<?php

namespace App\Domain\Display;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Ping to paired lobby displays: their queue, config or signage changed, or
 * the device was revoked. Each display listens on "display.{channelKey}"
 * (secret, rotated on revoke). One event may target many displays: the
 * Pusher protocol delivers it to all listed channels in a single request.
 */
class DisplayChanged implements ShouldBroadcastNow
{
    public const QUEUE = 'queue';

    public const CONFIG = 'config';

    public const SIGNAGE = 'signage';

    public const REVOKED = 'revoked';

    /** Pusher/Reverb accept at most 100 channels per trigger. */
    public const MAX_CHANNELS = 100;

    /** @var list<string> */
    private array $channelKeys;

    /** @param  string|list<string>  $channelKeys */
    public function __construct(
        string|array $channelKeys,
        public readonly string $reason,
        public readonly int $version = 0,
    ) {
        $this->channelKeys = is_string($channelKeys) ? [$channelKeys] : $channelKeys;
    }

    /** @return list<Channel> */
    public function broadcastOn(): array
    {
        return array_map(fn (string $key) => new Channel('display.'.$key), $this->channelKeys);
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
