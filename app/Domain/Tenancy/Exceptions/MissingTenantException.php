<?php

namespace App\Domain\Tenancy\Exceptions;

use RuntimeException;

class MissingTenantException extends RuntimeException
{
    public function __construct(string $message = 'No tenant is bound to the current context.')
    {
        parent::__construct($message);
    }
}
