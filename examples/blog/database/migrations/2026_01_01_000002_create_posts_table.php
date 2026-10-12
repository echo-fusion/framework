<?php

declare(strict_types=1);

use EchoFusion\Contracts\DatabaseManager\ConnectionInterface;
use EchoFusion\Framework\Database\Migration;

return new class implements Migration {
    public function up(ConnectionInterface $db): void
    {
        $db->execute('CREATE TABLE posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            title VARCHAR(200) NOT NULL,
            body TEXT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        )');
        $db->execute('CREATE INDEX posts_user_id ON posts (user_id)');
    }

    public function down(ConnectionInterface $db): void
    {
        $db->execute('DROP TABLE posts');
    }
};
