<?php

namespace App\Http\Controllers;

use App\Services\InvitationService;
use App\Services\PasswordPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ActivationController extends Controller
{
    public function show(Request $request, InvitationService $invitations): Response|JsonResponse
    {
        if ($request->expectsJson()) {
            $state = $invitations->setupState((string) $request->bearerToken());

            return response()->json([
                'state' => $state,
                'form' => $state === 'ready' ? view('auth.activation-form')->render() : null,
            ])->header('Cache-Control', 'private, no-store');
        }

        return response()->view('auth.activate')->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, InvitationService $invitations): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'], 'password' => ['required', 'string', PasswordPolicy::rule(), 'confirmed']]);
        $invitations->activate($data['token'], $data['password']);
        $request->session()->flash('status', 'Account activated. You can now sign in with your new password.');

        return response()->json(['redirect' => route('login')]);
    }
}
