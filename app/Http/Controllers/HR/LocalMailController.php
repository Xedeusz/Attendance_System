<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\LocalMailMessage;
use Illuminate\View\View;

class LocalMailController extends Controller
{
    public function index(): View
    {
        abort_unless(app()->environment('local'), 404);

        return view('hr.mail.index', ['messages' => LocalMailMessage::where('expires_at', '>', now())->latest('id')->paginate(15)]);
    }

    public function show(LocalMailMessage $message): View
    {
        abort_unless(app()->environment('local') && $message->expires_at->isFuture(), 404);
        $payload = $message->payload;
        preg_match('~https?://[^\s]+/activate-account#[a-f0-9]{64}~', $payload['text'], $matches);

        return view('hr.mail.show', ['message' => $message, 'payload' => $payload, 'activationLink' => $matches[0] ?? null]);
    }
}
