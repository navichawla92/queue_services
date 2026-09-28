<?php

namespace App\Domain\Queue;

use App\Domain\Organization\Models\Location;
use App\Domain\Queue\Events\QueueChanged;
use App\Domain\Queue\Models\LocationQueueState;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Serializes every queue mutation of one location (design Decision 4):
 * SELECT … FOR UPDATE on the location's location_queue_states row, run the
 * change, bump queue_version, and announce it after commit. Works on
 * MariaDB 10.4 (no SKIP LOCKED needed).
 */
class QueueLock
{
    /** @var array<int, list<array{type: string, ticket_id: int|null}>> pending changes per location in the open transaction */
    private array $pending = [];

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function run(Location $location, Closure $callback): mixed
    {
        return DB::transaction(function () use ($location, $callback) {
            DB::table('location_queue_states')->insertOrIgnore([
                'location_id' => $location->id,
                'tenant_id' => $location->tenant_id,
                'version' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $state = LocationQueueState::query()->whereKey($location->id)->lockForUpdate()->firstOrFail();
            $outermost = ! isset($this->pending[$location->id]);
            $this->pending[$location->id] ??= [];

            try {
                $result = $callback();
            } catch (\Throwable $e) {
                if ($outermost) {
                    unset($this->pending[$location->id]);
                }
                throw $e;
            }

            if ($outermost) {
                $changes = $this->pending[$location->id];
                unset($this->pending[$location->id]);

                if ($changes !== []) {
                    $state->increment('version');
                    $version = $state->version;
                    $tenantId = $location->tenant_id;
                    DB::afterCommit(fn () => QueueChanged::dispatch($tenantId, $location->id, $version, $changes));
                }
            }

            return $result;
        });
    }

    /** Record a change inside run(); announced once after commit. */
    public function changed(int $locationId, string $type, ?int $ticketId = null): void
    {
        $this->pending[$locationId][] = ['type' => $type, 'ticket_id' => $ticketId];
    }

    public function isHeld(int $locationId): bool
    {
        return isset($this->pending[$locationId]);
    }
}
