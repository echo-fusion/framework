<?php

declare(strict_types=1);

namespace Tests;

use EchoFusion\Contracts\DatabaseManager\ConnectionInterface;
use EchoFusion\Framework\Database\MigrationRunner;
use function dirname;
use function file_exists;
use function hash;
use function unlink;

/**
 * A fresh SQLite file, filled by the real migrations, with an admin and two authors that have API tokens.
 */
abstract class DatabaseTestCase extends TestCase
{
    protected const ADMIN = 'admin-token';

    protected const ANN = 'ann-token';

    protected const BOB = 'bob-token';

    protected function setUp(): void
    {
        $file = dirname(__DIR__) . '/storage/testing.sqlite';
        file_exists($file) && unlink($file);

        $db = $this->app()->getContainer()->get(ConnectionInterface::class);
        (new MigrationRunner($db, dirname(__DIR__) . '/database/migrations'))->migrate();

        foreach ([[1, 'Admin', 'admin', self::ADMIN], [2, 'Ann', 'user', self::ANN], [3, 'Bob', 'user', self::BOB]] as [$id, $name, $role, $token]) {
            $db->execute(
                'INSERT INTO users (id, name, email, password, role, api_token) VALUES (?, ?, ?, ?, ?, ?)',
                [$id, $name, strtolower($name) . '@example.com', 'x', $role, hash('sha256', $token)],
            );
        }
    }
}
