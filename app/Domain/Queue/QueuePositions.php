<?php

namespace App\Domain\Queue;

use App\Domain\Organization\Models\Location;
use App\Domain\Organization\Models\Service;
use App\Domain\Queue\Models\Ticket;
use App\Domain\Routing\EligibleEmployees;
use App\Domain\Routing\ServiceTimes;
use App\Domain\Routing\WaitEstimator;

/**
 * Live position and wait estimate of waiting tickets, computed on read so it
 * is always current after any queue change. estimateMany() does a whole
 * location in one ordered query (used by notifications and snapshots).
 */
class QueuePositions
{
    public function __construct(
        private readonly QueueOrdering $ordering,
        private readonly EligibleEmployees $eligible,
        private readonly ServiceTimes $serviceTimes,
        private readonly WaitEstimator $estimator,
    ) {}

    /** @return array{ahead: int, position: int, staff: int, minutes: int|null, label: string} */
    public function estimate(Ticket $ticket): array
    {
        $ahead = $this->ordering->ahead($ticket);
        $location = $ticket->location;
        $staff = $this->eligible->working($location, $ticket->department_id, $ticket->service_id)->count();

        return $this->result($ahead, $staff, $this->serviceTimes->averageMinutes($location, $ticket->service));
    }

    /**
     * Estimates for every waiting ticket of a location (same ordering as
     * QueueOrdering::ahead), with per-(department, service) staff counts and
     * per-service averages computed once.
     *
     * @return array<int, array{ahead: int, position: int, staff: int, minutes: int|null, label: string}> by ticket id
     */
    public function estimateMany(Location $location): array
    {
        $waiting = $this->ordering->ordered(
            Ticket::query()->where('location_id', $location->id)->where('status', TicketStatus::Waiting->value)
        )->get(['id', 'department_id', 'service_id']);

        $staff = [];
        $averages = [];
        $aheadByDepartment = [];
        $services = Service::query()->whereIn('id', $waiting->pluck('service_id')->unique())->get()->keyBy('id');
        $result = [];

        foreach ($waiting as $ticket) {
            $ahead = $aheadByDepartment[$ticket->department_id] ?? 0;
            $aheadByDepartment[$ticket->department_id] = $ahead + 1;

            $key = $ticket->department_id.':'.$ticket->service_id;
            $staff[$key] ??= $this->eligible->working($location, $ticket->department_id, $ticket->service_id)->count();
            $averages[$ticket->service_id] ??= ($service = $services->get($ticket->service_id))
                ? $this->serviceTimes->averageMinutes($location, $service)
                : 10.0;

            $result[$ticket->id] = $this->result($ahead, $staff[$key], $averages[$ticket->service_id]);
        }

        return $result;
    }

    /** @return array{ahead: int, position: int, staff: int, minutes: int|null, label: string} */
    private function result(int $ahead, int $staff, float $avgMinutes): array
    {
        $minutes = $this->estimator->minutes($ahead, $staff, $avgMinutes);

        return [
            'ahead' => $ahead,
            'position' => $ahead + 1,
            'staff' => $staff,
            'minutes' => $minutes,
            'label' => $this->estimator->label($minutes),
        ];
    }
}
