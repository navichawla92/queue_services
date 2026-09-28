<?php

namespace App\Domain\Display;

use App\Domain\Organization\Models\Location;
use App\Domain\Signage\Models\TickerMessage;

/** Scrolling ticker lines for a location's displays (digital-signage "Scrolling ticker"). */
class TickerMessages
{
    /** @return list<string> */
    public function for(Location $location): array
    {
        $today = now($location->effectiveTimezone())->toDateString();

        return TickerMessage::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('location_id')->orWhere('location_id', $location->id))
            ->where(fn ($q) => $q->whereNull('starts_on')->orWhereDate('starts_on', '<=', $today))
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today))
            ->orderBy('sort_order')->orderBy('id')
            ->pluck('body')->all();
    }
}
