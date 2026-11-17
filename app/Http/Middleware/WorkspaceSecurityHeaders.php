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
        if ($request->header('X-Workspace-Sync') === '1' || $request->routeIs('realtime.state') || $request->is('broadcasting/auth')) {
            $request->session()->reflash();
        }
        if ($request->header('X-Workspace-Sync') === '1' && $response->isSuccessful() && is_string($response->getContent())) {
            $parts = explode('<!--workspace:start-->', $response->getContent(), 2);
            if (count($parts) === 2) {
                $response->setContent(explode('<!--workspace:end-->', $parts[1], 2)[0]);
                $response->headers->remove('Content-Length');
            }
        }
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $capturePage = $request->routeIs('employee.attendance');
        $response->headers->set('Permissions-Policy', $capturePage ? 'camera=(self), microphone=(), geolocation=(self)' : 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
