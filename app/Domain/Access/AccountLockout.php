<?php

namespace App\Domain\Access;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\LoginRateLimiter;

/**
 * Per-account lockout (access-control spec): after MAX_ATTEMPTS failed
 * sign-ins within DECAY_SECONDS the account is blocked for DECAY_SECONDS.
 * Only failures count; a successful sign-in clears the counter.
 */
class AccountLockout extends LoginRateLimiter
{
    public const MAX_ATTEMPTS = 5;

    public const DECAY_SECONDS = 15 * 60;

    public function tooManyAttempts(Request $request)
    {
        return $this->limiter->tooManyAttempts($this->throttleKey($request), self::MAX_ATTEMPTS);
    }

    public function increment(Request $request)
    {
        $this->limiter->hit($this->throttleKey($request), self::DECAY_SECONDS);
    }

    protected function throttleKey(Request $request)
    {
        return 'login-lockout:'.Str::transliterate(Str::lower((string) $request->input(Fortify::username())));
    }
}
