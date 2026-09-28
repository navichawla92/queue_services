<?php

namespace App\Domain\Access;

use App\Domain\Organization\Models\Location;

/** The location a staff user is currently working in (request-scoped). */
class CurrentLocation
{
    private ?Location $location = null;

    public function set(?Location $location): void
    {
        $this->location = $location;
    }

    public function get(): ?Location
    {
        return $this->location;
    }

    public function require(): Location
    {
        return $this->location ?? abort(403, __('No location is assigned to your account.'));
    }
}
