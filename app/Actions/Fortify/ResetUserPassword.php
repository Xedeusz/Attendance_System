<?php

namespace App\Actions\Fortify;

use App\Services\PasswordPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    public function reset($user, array $input): void
    {
        Validator::make($input, [
            'password' => ['required', 'string', PasswordPolicy::rule(), 'confirmed'],
        ])->validate();
        DB::transaction(function () use ($user, $input) {
            $user->forceFill(['password' => Hash::make($input['password']), 'remember_token' => Str::random(60)])->save();
            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->getKey())->delete();
            }
        });
    }
}
