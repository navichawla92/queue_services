<?php

namespace App\Domain\Organization;

enum EmployeeStatus: string
{
    case Available = 'available';
    case Busy = 'busy';
    case OnBreak = 'on_break';
    case Offline = 'offline';

    public function label(): string
    {
        return match ($this) {
            self::Available => __('Available'),
            self::Busy => __('Busy'),
            self::OnBreak => __('On break'),
            self::Offline => __('Offline'),
        };
    }

    /** Only available employees are offered new tickets by routing. */
    public function acceptsNewTickets(): bool
    {
        return $this === self::Available;
    }

    /** Counted as working capacity for wait estimates (routing spec). */
    public function isWorking(): bool
    {
        return $this === self::Available || $this === self::Busy;
    }
}
