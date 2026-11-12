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
    'features' => env('AUTH_PASSWORD_RESET_ENABLED', false) ? [Features::resetPasswords()] : [],
];
