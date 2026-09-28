<?php

namespace App\Domain\Queue;

enum TicketStatus: string
{
    case Waiting = 'waiting';
    case Called = 'called';
    case InService = 'in_service';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case NoShow = 'no_show';
    case Cancelled = 'cancelled';           // by the customer ("leave queue")
    case ClosedUnserved = 'closed_unserved'; // end-of-day closeout

    /** Allowed transitions (staff-queue spec "Ticket lifecycle"). */
    public function canTransitionTo(self $to): bool
    {
        return in_array($to, match ($this) {
            self::Waiting => [self::Called, self::OnHold, self::Cancelled, self::NoShow, self::ClosedUnserved, self::Waiting],
            self::Called => [self::InService, self::Waiting, self::NoShow, self::Called],
            self::InService => [self::Completed, self::OnHold, self::Waiting],
            self::OnHold => [self::Waiting, self::ClosedUnserved],
            default => [],
        }, true);
    }

    /** Still part of today's live queue. */
    public function isActive(): bool
    {
        return in_array($this, [self::Waiting, self::Called, self::InService, self::OnHold], true);
    }

    public function isFinal(): bool
    {
        return ! $this->isActive();
    }

    /** @return list<string> */
    public static function activeValues(): array
    {
        return [self::Waiting->value, self::Called->value, self::InService->value, self::OnHold->value];
    }

    public function label(): string
    {
        return match ($this) {
            self::Waiting => __('Waiting'),
            self::Called => __('Called'),
            self::InService => __('In service'),
            self::OnHold => __('On hold'),
            self::Completed => __('Completed'),
            self::NoShow => __('No-show'),
            self::Cancelled => __('Left queue'),
            self::ClosedUnserved => __('Closed unserved'),
        };
    }
}
