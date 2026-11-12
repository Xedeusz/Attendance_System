<?php

use Laravel\Fortify\Features;

return [
    'guard' => 'web',
    'middleware' => ['web'],
    'auth_middleware' => 'auth',
    'passwords' => 'users',
    'username' => 'email',
    'email' => 'email',
    'lowercase_usernames' => true,
    'home' => '/dashboard',
    'prefix' => '',
    'domain' => null,
    'views' => true,
    'limiters' => ['login' => 'login'],
    // Registration stays disabled. Enable recovery only after testing delivery.
    'features' => array_merge([Features::updatePasswords()],
        env('AUTH_PASSWORD_RESET_ENABLED', false)
        && (env('MAIL_MAILER') === 'smtp' || (env('MAIL_MAILER') === 'private_local' && env('APP_ENV') === 'local'))
            ? [Features::resetPasswords()] : []),
];
