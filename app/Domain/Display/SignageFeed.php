<?php

namespace App\Domain\Display;

use App\Domain\Access\Models\Device;

/** Hash of the display's current signage manifest (filled by digital signage). */
class SignageFeed
{
    public function hash(Device $device): ?string
    {
        return null;
    }
}
