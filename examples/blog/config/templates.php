<?php

declare(strict_types=1);

use function EchoFusion\Framework\env;

$base = \dirname(__DIR__);

return [
    'paths' => [$base . '/resources/templates'],

    // variables every template can use
    'globals' => [
        'appName' => env('APP_NAME', 'EchoFusion Blog'),
    ],

    // .tpl is the built-in template language; .php are plain PHP templates. Add 'twig' => [] after `composer require twig/twig`.
    'engines' => [
        'tpl' => ['options' => ['cache' => $base . '/storage/cache/templates', 'auto_reload' => (bool) env('APP_DEBUG', false)]],
        'php' => [],
    ],
];
