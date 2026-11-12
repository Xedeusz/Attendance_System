<?php

namespace App\Actions\Fortify;

use App\Services\PasswordPolicy;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

class UpdateUserPassword implements UpdatesUserPasswords
{
    public function update($user, array $input): void
    {
        Validator::make($input, ['current_password' => ['required', 'current_password:web'], 'password' => ['required', 'string', PasswordPolicy::rule(), 'confirmed']])->validateWithBag('updatePassword');
        app(ResetUserPassword::class)->reset($user, $input);
        // End other sessions; keep only this freshly authenticated session.
        if (request()->hasSession()) {
            request()->session()->put('password_hash_web', $user->password);
        }
    }
}
