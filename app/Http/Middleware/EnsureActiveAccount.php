<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            $version = $request->session()->get('account_version', $user->session_version);
            if (! $user->canAuthenticate() || (int) $version !== $user->session_version) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors(['email' => 'This account or session is no longer available. Contact HR.']);
            }
            $request->session()->put('account_version', $user->session_version);
        }

        return $next($request);
    }
}
