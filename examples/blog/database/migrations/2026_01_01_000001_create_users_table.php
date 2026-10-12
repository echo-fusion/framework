<?php

declare(strict_types=1);

use EchoFusion\Contracts\DatabaseManager\ConnectionInterface;
use EchoFusion\Framework\Database\Migration;

return new class implements Migration {
    public function up(ConnectionInterface $db): void
    {
        // api_token holds the SHA-256 hash of the token, never the token itself
        $db->execute('CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(190) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            api_token VARCHAR(64) NULL UNIQUE,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');
    }

    public function down(ConnectionInterface $db): void
    {
        $db->execute('DROP TABLE users');
    }
};
