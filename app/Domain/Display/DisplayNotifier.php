<?php

namespace App\Domain\Display;

use App\Domain\Access\Models\Device;
use App\Domain\Queue\Broadcasting\LiveUpdates;

/** Pings paired lobby displays (by location or one device) in as few publishes as possible. */
class DisplayNotifier
{
    public function __construct(private readonly LiveUpdates $live) {}

    public function location(int $locationId, string $reason, int $version = 0): void
    {
        Device::query()
            ->where('location_id', $locationId)
            ->where('type', Device::TYPE_DISPLAY)
            ->whereNull('revoked_at')
            ->whereNotNull('channel_key')
            ->pluck('channel_key')
            ->chunk(DisplayChanged::MAX_CHANNELS)
            ->each(fn ($keys) => $this->live->publish(new DisplayChanged($keys->values()->all(), $reason, $version)));
    }

    public function device(Device $device, string $reason): void
    {
        if ($device->channel_key) {
            $this->live->publish(new DisplayChanged($device->channel_key, $reason));
        }
    }
}
