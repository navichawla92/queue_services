<?php

namespace App\Domain\Access;

use App\Domain\Access\Models\Device;

/** The paired device making the current request (set by AuthenticateDevice). */
class DeviceContext
{
    private ?Device $device = null;

    public function set(?Device $device): void
    {
        $this->device = $device;
    }

    public function get(): ?Device
    {
        return $this->device;
    }

    public function require(): Device
    {
        return $this->device ?? abort(401);
    }
}
