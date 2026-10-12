<?php

declare(strict_types=1);

use function EchoFusion\Framework\env;

$base = \dirname(__DIR__);

// Nothing connects until the first query.
return [
    'default' => 'main',

    'connections' => [
        'main' => [
            'driver' => env('DB_DRIVER', 'sqlite'),       // mysql, pgsql or sqlite
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT'),
            'dbname' => env('DB_DATABASE'),
            'user' => env('DB_USERNAME'),
            'password' => env('DB_PASSWORD'),
            'path' => $base . '/' . env('DB_DATABASE', 'storage/database.sqlite'),   // sqlite file
        ],

        // Use Doctrine DBAL or any other implementation of the connection contract instead of the built-in one:
        // 'orm' => ['class' => EchoFusion\DatabaseManager\Connections\DoctrineConnection::class, 'driver' => 'pdo_mysql', ...],
    ],
];
