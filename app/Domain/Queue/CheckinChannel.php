<?php

namespace App\Domain\Queue;

enum CheckinChannel: string
{
    case Kiosk = 'kiosk';
    case Qr = 'qr';
    case Mobile = 'mobile';
    case Receptionist = 'receptionist';

    public function isSelfService(): bool
    {
        return $this !== self::Receptionist;
    }
}
