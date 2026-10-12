<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use function EchoFusion\Framework\env;

return [
    'name' => env('APP_NAME', 'EchoFusion Blog'),

    // true shows exception details in the browser; keep it false in production
    'debug' => env('APP_DEBUG', false),

    'timezone' => env('APP_TIMEZONE', 'UTC'),

    // Paths that need no CSRF token: APIs that authenticate with a header token.
    'csrf' => ['except' => ['/api/*']],

    // Modules add config, providers, routes and templates. Classes implementing ModuleInterface.
    'modules' => [],

    // Your own service providers. `Provider::class => 'section'` boots it with config/<section>.php.
    'providers' => [
        AppServiceProvider::class,
    ],

    // Plugins run code at start-up for the environments they support (a debug bar, an error tracker ...).
    'plugins' => [],
];
