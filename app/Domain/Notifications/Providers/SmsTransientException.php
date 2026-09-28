<?php

namespace App\Domain\Notifications\Providers;

use RuntimeException;

/** Retryable provider failure (outage, rate limit, timeout). */
class SmsTransientException extends RuntimeException {}
