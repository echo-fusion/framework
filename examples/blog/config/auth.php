<?php

declare(strict_types=1);

/*
 * Authentication is configured but unused until a route has the 'auth' middleware or your code asks for a guard.
 * The 'users' provider reads the table `users` (id, password, and optionally api_token as a SHA-256 hash).
 */
return [
    'default' => 'web',

    'guards' => [
        'web' => ['driver' => 'session', 'provider' => 'users'],                       // browsers: the user id lives in the session
        'api' => ['driver' => 'token', 'provider' => 'users', 'header' => 'Authorization', 'scheme' => 'Bearer'],
    ],

    'providers' => [
        'users' => ['driver' => 'database', 'table' => 'users', 'id' => 'id', 'password' => 'password', 'token' => 'api_token'],
    ],

    'hasher' => ['algorithm' => 'bcrypt', 'options' => ['cost' => 12]],

    // where the 'auth' and 'guest' route middleware send people (JSON clients get 401 / 403 instead)
    'middleware' => [
        'authenticate' => ['guard' => 'web', 'redirect_to' => '/login'],
        'guest' => ['guard' => 'web', 'redirect_to' => '/'],
    ],
];
