<?php

namespace App\Domain\Scheduling;

enum AppointmentStatus: string
{
    case Booked = 'booked';
    case Confirmed = 'confirmed';
    case Arrived = 'arrived';
    case InService = 'in_service';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    /** Holds its time slot (blocks availability). */
    public function occupiesSlot(): bool
    {
        return in_array($this, [self::Booked, self::Confirmed, self::Arrived, self::InService], true);
    }

    /** Not yet arrived: can be checked in, rescheduled, cancelled. */
    public function isUpcoming(): bool
    {
        return in_array($this, [self::Booked, self::Confirmed], true);
    }

    /** @return list<string> */
    public static function slotValues(): array
    {
        return [self::Booked->value, self::Confirmed->value, self::Arrived->value, self::InService->value];
    }

    public function label(): string
    {
        return match ($this) {
            self::Booked => __('Booked'),
            self::Confirmed => __('Confirmed'),
            self::Arrived => __('Arrived'),
            self::InService => __('In service'),
            self::Completed => __('Completed'),
            self::Cancelled => __('Cancelled'),
            self::NoShow => __('No-show'),
        };
    }
}
