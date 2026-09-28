<?php

namespace App\Domain\Queue;

use App\Domain\Organization\Models\Location;
use App\Domain\Organization\OperatingHours;
use App\Domain\Queue\Exceptions\QueueActionException;
use App\Domain\Queue\Models\Ticket;
use App\Domain\Tenancy\TenantContext;
use Carbon\CarbonImmutable;

/**
 * Scheduled queue upkeep for the current tenant (staff-queue "Mark no-show"
 * auto-no-show and "End-of-day handling").
 */
class QueueHousekeeping
{
    public function __construct(
        private readonly TicketStateMachine $queue,
        private readonly OperatingHours $hours,
    ) {}

    /** Called tickets not started within the tenant's auto-no-show minutes. */
    public function autoNoShow(): int
    {
        $tenant = app(TenantContext::class)->require();
        $minutes = $tenant->settings()->get('auto_no_show_minutes');
        if (! $minutes) {
            return 0;
        }

        $count = 0;
        Ticket::query()->with('location')
            ->where('status', TicketStatus::Called->value)
            ->where('called_at', '<=', now()->subMinutes((int) $minutes))
            ->each(function (Ticket $ticket) use (&$count) {
                try {
                    $this->queue->noShow($ticket, Actor::system());
                    $count++;
                } catch (QueueActionException) {
                    // Moved on meanwhile (started / recalled) — skip.
                }
            });

        return $count;
    }

    /**
     * Close tickets still waiting or on hold: those from earlier local days,
     * and today's once the location's last window closed + buffer.
     */
    public function closeout(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $count = 0;

        Location::query()->each(function (Location $location) use ($now, &$count) {
            $buffer = (int) $location->tenant->settings()->get('closeout_buffer_minutes', 60);
            $local = $now->setTimezone($location->effectiveTimezone());
            $windows = $this->hours->windows($location, $local);
            $lastClose = $windows === [] ? null : end($windows)[1];
            $pastClosing = $lastClose !== null && $local->greaterThanOrEqualTo($lastClose->addMinutes($buffer));

            Ticket::query()
                ->where('location_id', $location->id)
                ->whereIn('status', [TicketStatus::Waiting->value, TicketStatus::OnHold->value])
                ->where(fn ($q) => $pastClosing
                    ? $q->whereDate('local_date', '<=', $local->toDateString())
                    : $q->whereDate('local_date', '<', $local->toDateString()))
                ->each(function (Ticket $ticket) use (&$count) {
                    $this->queue->closeUnserved($ticket);
                    $count++;
                });
        });

        return $count;
    }
}
