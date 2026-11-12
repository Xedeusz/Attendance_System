<?php

namespace App\Services;

use Illuminate\Validation\Rules\Password;

class PasswordPolicy
{
    public static function rule(): Password
    {
        return Password::min(12)->letters()->mixedCase()->numbers()->symbols();
    }
}
