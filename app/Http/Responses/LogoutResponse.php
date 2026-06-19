<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;

/**
 * Visible session termination on logout (STIG APSC-DV-000100, NIST AC-12(2)).
 * Login view surfaces session('status').
 */
class LogoutResponse implements LogoutResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        /** @var Request $request */
        return redirect()->route('login')->with('status', 'You have been signed out.');
    }
}
