<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse;

class RoleLoginResponse implements LoginResponse
{
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }
        if ($request->session()->get('url.intended') === route('leave.verify.scan')) {
            $request->session()->forget('url.intended');

            return redirect()->route('leave.verify.scan');
        }

        return redirect()->route($request->user()->workspaceRoute());
    }
}
