<?php

namespace App\Http\Controllers;

use App\Services\InvitationService;
use App\Services\PasswordPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivationController extends Controller
{
    public function show(): View
    {
        return view('auth.activate');
    }

    public function store(Request $request, InvitationService $invitations): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'], 'password' => ['required', 'string', PasswordPolicy::rule(), 'confirmed']]);
        $invitations->activate($data['token'], $data['password']);
        $request->session()->flash('status', 'Account activated. You can now sign in with your new password.');

        return response()->json(['redirect' => route('login')]);
    }
}
