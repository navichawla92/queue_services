<?php

namespace App\Domain\Analytics;

use App\Domain\Analytics\Models\DailyStat;
use App\Domain\Organization\Models\Location;
use App\Domain\Queue\Models\Ticket;
use App\Domain\Queue\TicketStatus;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates tickets into daily_stats rows (design Decision 10). The same
 * aggregation serves the nightly rollup and "today" (always live). Hours are
 * computed in PHP in each location's zone (portable: no CONVERT_TZ tables).
 */
class StatsRollup
{
    /**
     * Aggregate tickets into rollup-shaped rows (not saved).
     *
     * @param  Builder<Ticket>  $tickets
     * @return list<array<string, mixed>>
     */
    public function aggregate(Builder $tickets): array
    {
        $zones = Location::query()->get()->mapWithKeys(fn (Location $l) => [$l->id => $l->effectiveTimezone()]);
        /** @var array<string, array<string, mixed>> $rows filled by reference in the chunk callback */
        $rows = [];

        $tickets->select([
            'id', 'local_date', 'location_id', 'department_id', 'service_id', 'serving_employee_id',
            'customer_type', 'status', 'checked_in_at', 'wait_seconds', 'service_seconds',
        ])->orderBy('id')->chunk(2000, function ($chunk) use (&$rows, $zones) {
            foreach ($chunk as $t) {
                $key = implode('|', [$t->local_date->format('Y-m-d'), $t->location_id, $t->department_id, $t->service_id, $t->serving_employee_id ?? 0, $t->customer_type->value]);
                $rows[$key] ??= [
                    'local_date' => $t->local_date->format('Y-m-d'),
                    'location_id' => $t->location_id,
                    'department_id' => $t->department_id,
                    'service_id' => $t->service_id,
                    'employee_id' => $t->serving_employee_id ?? 0,
                    'customer_type' => $t->customer_type->value,
                    'tickets' => 0, 'completed' => 0, 'no_shows' => 0, 'cancelled' => 0, 'closed_unserved' => 0,
                    'wait_sum' => 0, 'wait_count' => 0, 'service_sum' => 0, 'service_count' => 0,
                    'checkins_by_hour' => array_fill(0, 24, 0),
                ];
                $row = &$rows[$key];

                $row['tickets']++;
                match ($t->status) {
                    TicketStatus::Completed => $row['completed']++,
                    TicketStatus::NoShow => $row['no_shows']++,
                    TicketStatus::Cancelled => $row['cancelled']++,
                    TicketStatus::ClosedUnserved => $row['closed_unserved']++,
                    default => null,
                };
                if ($t->wait_seconds !== null) {
                    $row['wait_sum'] += $t->wait_seconds;
                    $row['wait_count']++;
                }
                if ($t->status === TicketStatus::Completed && $t->service_seconds !== null) {
                    $row['service_sum'] += $t->service_seconds;
                    $row['service_count']++;
                }
                $hour = (int) $t->checked_in_at->copy()->setTimezone($zones[$t->location_id] ?? 'UTC')->format('G');
                $row['checkins_by_hour'][$hour]++;
                unset($row);
            }
        });

        return array_values($rows);
    }

    /** Rebuild one local date for the current tenant (idempotent). */
    public function rollupDay(string $localDate): int
    {
        $rows = $this->aggregate(Ticket::query()->whereDate('local_date', $localDate));

        DB::transaction(function () use ($localDate, $rows) {
            DailyStat::query()->whereDate('local_date', $localDate)->delete();
            foreach ($rows as $row) {
                DailyStat::create($row);
            }
        });

        return count($rows);
    }

    /** Nightly: recompute the trailing days (late changes are picked up). */
    public function rollupRecent(int $days = 7): void
    {
        $tz = app(TenantContext::class)->require()->settings()->timezone();
        for ($d = 1; $d <= $days; $d++) {
            $this->rollupDay(now($tz)->subDays($d)->toDateString());
        }
    }
}
