<?php

namespace App\Domain\Queue;

use App\Domain\Queue\Models\Ticket;
use App\Domain\Routing\EligibleEmployees;
use App\Domain\Routing\ServiceTimes;
use App\Domain\Routing\WaitEstimator;

/**
 * Live position and wait estimate of a waiting ticket, computed on read so it
 * is always current after any queue change.
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
        $minutes = $this->estimator->minutes($ahead, $staff, $this->serviceTimes->averageMinutes($location, $ticket->service));

        return [
            'ahead' => $ahead,
            'position' => $ahead + 1,
            'staff' => $staff,
            'minutes' => $minutes,
            'label' => $this->estimator->label($minutes),
        ];
    }
}
