<?php

namespace App\Domain\Queue\Exceptions;

use RuntimeException;

/** No department / eligible staff for the service here (customer-routing spec). */
class UnroutableServiceException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('This service is not available right now. Please see the receptionist.'));
    }
}
