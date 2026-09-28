<?php

namespace App\Domain\Notifications\Providers;

use RuntimeException;

/** Non-retryable provider failure (invalid number, auth error, blocked). */
class SmsPermanentException extends RuntimeException
{
    public function __construct(string $message, public readonly ?string $errorCode = null)
    {
        parent::__construct($message);
    }
}
