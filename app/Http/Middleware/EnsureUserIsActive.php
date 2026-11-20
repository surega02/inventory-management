<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Kicks out a user deactivated while their session was still open. */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            abort_if($request->expectsJson(), 403, 'Your account is inactive.');

            return redirect()->route('login')->withErrors(['email' => 'Your account is inactive.']);
        }

        return $next($request);
    }
}
