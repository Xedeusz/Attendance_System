<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class WorkspaceSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('POST') && $request->routeIs('password.email', 'password.update', 'password.confirm.store')) {
            $key = 'recovery:'.hash('sha256', (string) $request->ip());
            if (RateLimiter::tooManyAttempts($key, 5)) {
                abort(429, 'Please wait before trying password recovery again.');
            }
            RateLimiter::hit($key, 60);
        }
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'same-origin');
        // Phase 2 has no camera or location functionality. Revisit in their phases.
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
