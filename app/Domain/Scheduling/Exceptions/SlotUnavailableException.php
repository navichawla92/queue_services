<?php

namespace App\Domain\Scheduling\Exceptions;

use RuntimeException;

class SlotUnavailableException extends RuntimeException
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? __('Sorry, that time was just taken. Please choose another time.'));
    }
}
