<?php

namespace App\Http\Responses;

use App\Models\PrivacyNotice;
use Laravel\Fortify\Contracts\LoginResponse;
use Symfony\Component\HttpFoundation\Response;

class RoleLoginResponse implements LoginResponse
{
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }
        if (PrivacyNotice::pendingFor($request->user())) {
            return redirect()->route('privacy.index');
        }
        if ($request->session()->get('url.intended') === route('leave.verify.scan')) {
            $request->session()->forget('url.intended');

            return redirect()->route('leave.verify.scan');
        }

        return redirect()->route($request->user()->workspaceRoute());
    }
}
