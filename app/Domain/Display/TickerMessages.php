<?php

namespace App\Domain\Display;

use App\Domain\Organization\Models\Location;

/** Scrolling ticker lines for a location's displays (filled by digital signage). */
class TickerMessages
{
    /** @return list<string> */
    public function for(Location $location): array
    {
        return [];
    }
}
