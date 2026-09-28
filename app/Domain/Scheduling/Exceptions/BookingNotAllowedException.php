<?php

namespace App\Domain\Scheduling\Exceptions;

use RuntimeException;

/** Booking/change refused by policy (disabled, cutoff, no-show limit); message is user-facing. */
class BookingNotAllowedException extends RuntimeException {}
