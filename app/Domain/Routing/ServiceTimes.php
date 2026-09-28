<?php

namespace App\Domain\Routing;

use App\Domain\Organization\Models\Location;
use App\Domain\Organization\Models\Service;
use App\Domain\Queue\Models\Ticket;
use App\Domain\Queue\TicketStatus;
use App\Domain\Routing\Models\ServiceTimeStat;

/**
 * Rolling average service time per location & service (design Decision 7):
 * 14-day window, refreshed hourly; below MIN_SAMPLES the service's expected
 * duration is used instead.
 */
class ServiceTimes
{
    public const WINDOW_DAYS = 14;

    public const MIN_SAMPLES = 20;

    public function averageMinutes(Location $location, Service $service): float
    {
        $stat = ServiceTimeStat::query()
            ->where('location_id', $location->id)
            ->where('service_id', $service->id)
            ->first();

        if ($stat === null || $stat->sample_count < self::MIN_SAMPLES) {
            return (float) $service->expected_minutes;
        }

        return $stat->avg_seconds / 60;
    }

    /** Recompute all stats of the current tenant. */
    public function refresh(): void
    {
        $rows = Ticket::query()
            ->where('status', TicketStatus::Completed->value)
            ->whereNotNull('service_seconds')
            ->where('completed_at', '>=', now()->subDays(self::WINDOW_DAYS))
            ->groupBy('location_id', 'service_id')
            ->selectRaw('location_id, service_id, AVG(service_seconds) as avg_seconds, COUNT(*) as samples')
            ->toBase()
            ->get();

        foreach ($rows as $row) {
            ServiceTimeStat::query()->updateOrCreate(
                ['location_id' => $row->location_id, 'service_id' => $row->service_id],
                ['avg_seconds' => (int) round((float) $row->avg_seconds), 'sample_count' => (int) $row->samples, 'computed_at' => now()],
            );
        }
    }
}
