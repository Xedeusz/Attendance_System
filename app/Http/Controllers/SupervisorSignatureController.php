<?php

namespace App\Http\Controllers;

use App\Http\Requests\SupervisorSignatureRequest;
use App\Models\SupervisorSignature;
use App\Services\SupervisorSignatureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SupervisorSignatureController extends Controller
{
    public function show(Request $request): Response
    {
        $actor = $request->user();
        abort_unless($actor->isSupervisor() && $actor->canAuthenticate(), 403);

        return response()->view('workspace.electronic-signature', [
            'signature' => SupervisorSignature::activeFor($actor)->first(),
        ], 200, ['Cache-Control' => 'private, no-store']);
    }

    public function image(Request $request, SupervisorSignatureService $signatures): Response
    {
        $actor = $request->user();
        abort_unless($actor->isSupervisor() && $actor->canAuthenticate(), 403);
        $signature = SupervisorSignature::activeFor($actor)->firstOrFail();

        return response($signatures->bytes($signature), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ]);
    }

    public function store(SupervisorSignatureRequest $request, SupervisorSignatureService $signatures): RedirectResponse
    {
        $signatures->save($request->user(), $request->file('signature'));

        return redirect()->route('supervisor.signature.show')->with('status', 'Electronic signature saved.');
    }
}
