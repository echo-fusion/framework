<?php

declare(strict_types=1);

use function EchoFusion\Framework\env;

return [
    'name' => 'echofusion_session',
    'lifetime' => 0,                                       // 0: until the browser closes
    'secure' => (bool) env('SESSION_SECURE', false),       // true: only send the cookie over HTTPS
    'http_only' => true,
    'same_site' => 'Lax',
    'save_path' => \dirname(__DIR__) . '/storage/sessions',
];
