<?php

namespace App\Domain\Queue;

enum CustomerType: string
{
    case WalkIn = 'walk_in';
    case Appointment = 'appointment';
}
