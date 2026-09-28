<?php

namespace App\Domain\Queue\Exceptions;

use App\Domain\Queue\Models\Ticket;
use RuntimeException;

/** The phone number already holds an active ticket at this location. */
class DuplicateCheckinException extends RuntimeException
{
    public function __construct(public readonly Ticket $existing)
    {
        parent::__construct(__('You are already checked in with ticket :number.', ['number' => $existing->number]));
    }
}
