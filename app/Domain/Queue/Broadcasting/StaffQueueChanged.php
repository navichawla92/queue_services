<?php

namespace App\Domain\Queue\Broadcasting;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * To staff dashboards of a location (private, authorized per tenant and
 * location). Only version + ticket ids: dashboards reload their state.
 */
class StaffQueueChanged implements ShouldBroadcastNow
{
    /** @param  list<array{type: string, ticket_id: int|null}>  $changes */
    public function __construct(
        public readonly int $tenantId,
        public readonly int $locationId,
        public readonly int $version,
        public readonly array $changes,
    ) {}

    public static function channelName(int $tenantId, int $locationId): string
    {
        return "tenant.{$tenantId}.location.{$locationId}.queue";
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel(self::channelName($this->tenantId, $this->locationId));
    }

    public function broadcastAs(): string
    {
        return 'queue.changed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['version' => $this->version, 'changes' => $this->changes];
    }
}
