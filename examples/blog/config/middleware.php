<?php

declare(strict_types=1);

use App\Http\Middleware\AuthenticateApi;
use App\Http\Middleware\RequireAdmin;
use EchoFusion\AuthManager\Middleware\AuthenticateMiddleware;
use EchoFusion\AuthManager\Middleware\GuestMiddleware;
use EchoFusion\Framework\Http\Middleware\VerifyCsrfToken;
use EchoFusion\SessionManager\Middleware\StartSessionMiddleware;

return [
    // Run for every request, in this order. The first one wraps all the others.
    'core' => [
        StartSessionMiddleware::class,
        VerifyCsrfToken::class, // forms must send the _token field (see README: CSRF protection)
    ],

    // Names you can use in a route: 'middlewares' => ['auth']
    'route' => [
        'auth' => AuthenticateMiddleware::class,
        'guest' => GuestMiddleware::class,
        'admin' => RequireAdmin::class,    // use after 'auth': ['auth', 'admin']
        'api' => AuthenticateApi::class,   // a valid `Authorization: Bearer <token>` header
    ],
];
