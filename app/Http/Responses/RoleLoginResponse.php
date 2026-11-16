<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse;

class RoleLoginResponse implements LoginResponse
{
    public function toResponse($request)
    {
        return $request->wantsJson() ? response()->json(['two_factor' => false]) : redirect()->route($request->user()->workspaceRoute());
    }
}
