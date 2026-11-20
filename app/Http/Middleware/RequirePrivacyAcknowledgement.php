<?php

namespace App\Http\Middleware;

use App\Models\PrivacyNotice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePrivacyAcknowledgement
{
    public function handle(Request $request, Closure $next): Response
    {
        if (PrivacyNotice::pendingFor($request->user())) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Acknowledge the current Data Privacy Notice to continue.',
                    'notice_url' => route('privacy.index'),
                ], 403);
            }

            if ($request->routeIs('leave.verify.scan') && $request->session()->has('leave_scan_hash')) {
                $request->session()->put('url.intended', route('leave.verify.scan'));
            }

            return redirect()->route('privacy.index', status: 303);
        }

        return $next($request);
    }
}
