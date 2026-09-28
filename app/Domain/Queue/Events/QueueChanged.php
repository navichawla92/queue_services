<?php

namespace App\Domain\Queue\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A location's queue changed (after commit). Carries the new queue_version so
 * clients can detect gaps and resync (design Decision 5).
 */
class QueueChanged
{
    use Dispatchable;

    /** @param  list<array{type: string, ticket_id: int|null}>  $changes */
    public function __construct(
        public readonly int $tenantId,
        public readonly int $locationId,
        public readonly int $version,
        public readonly array $changes,
    ) {}

    /** @return list<int> */
    public function ticketIds(): array
    {
        return array_values(array_unique(array_filter(array_column($this->changes, 'ticket_id'))));
    }

    public function has(string $type): bool
    {
        return in_array($type, array_column($this->changes, 'type'), true);
    }
}
