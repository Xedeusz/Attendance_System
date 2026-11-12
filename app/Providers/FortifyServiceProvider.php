<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Http\Responses\RecoveryLinkResponse;
use App\Http\Responses\RoleLoginResponse;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, RecoveryLinkResponse::class);
        $this->app->bind(SuccessfulPasswordResetLinkRequestResponse::class, RecoveryLinkResponse::class);
        $this->app->bind(LoginResponse::class, RoleLoginResponse::class);
    }

    public function boot(): void
    {
        auth()->guard('web')->setRememberDuration(10080);
        Fortify::loginView(fn () => view('auth.login'));
        Fortify::confirmPasswordView(fn () => view('auth.confirm-password'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', ['request' => $request]));
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::authenticateUsing(function (Request $request) {
            $user = User::where('email', mb_strtolower((string) $request->input('email')))->first();
            if ($user && Hash::check((string) $request->input('password'), $user->password) && $user->canAuthenticate()) {
                return $user;
            }

            return null;
        });
        RateLimiter::for('login', function (Request $request) {
            $key = hash('sha256', Str::lower((string) $request->input('email')).'|'.$request->ip());

            return [Limit::perMinute(5)->by($key), Limit::perMinute(30)->by((string) $request->ip())];
        });
    }
}
