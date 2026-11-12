<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;

class RecordLogin
{
    public function handle(Login $event): void
    {
        if ($event->user->canAuthenticate()) {
            $event->user->forceFill(['last_login_at' => now()])->save();
            if (request()->hasSession()) {
                request()->session()->put('account_version', $event->user->session_version);
            }
        }
    }
}
