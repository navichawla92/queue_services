<?php

namespace App\Http\Responses;

use App\Domain\Access\HomeRoute;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

/** Sends each user to the default screen for their role. */
class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        $home = HomeRoute::for($request->user());

        return $request->wantsJson()
            ? response()->json(['two_factor' => false, 'redirect' => $home])
            : redirect()->intended($home);
    }
}
