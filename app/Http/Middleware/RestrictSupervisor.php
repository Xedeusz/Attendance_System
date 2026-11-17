<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictSupervisor
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isSupervisor()) {
            abort_unless($request->is('profile/photo/*', 'broadcasting/auth', 'realtime/state', 'supervisor/leave', 'supervisor/leave/*', 'employee/leave', 'employee/leave/*', 'leave', 'dashboard', 'profile', 'settings', 'user/password', 'user/confirm-password', 'confirm-password', 'logout', 'login', 'forgot-password', 'reset-password', 'reset-password/*'), 403);
        }

        return $next($request);
    }
}
