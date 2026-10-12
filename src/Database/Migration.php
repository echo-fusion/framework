<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Database;

use EchoFusion\Contracts\DatabaseManager\ConnectionInterface;

/**
 * A change to the database structure. A migration file in `database/migrations` returns one:
 *
 * <code>
 * return new class implements Migration {
 *     public function up(ConnectionInterface $db): void
 *     {
 *         $db->execute('CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, title VARCHAR(200) NOT NULL)');
 *     }
 *
 *     public function down(ConnectionInterface $db): void
 *     {
 *         $db->execute('DROP TABLE posts');
 *     }
 * };
 * </code>
 *
 * SQL differs between databases (`AUTOINCREMENT` in SQLite, `AUTO_INCREMENT` in MySQL, `SERIAL` in PostgreSQL), so write
 * what your database understands, or branch on the driver.
 */
interface Migration
{
    public function up(ConnectionInterface $db): void;

    public function down(ConnectionInterface $db): void;
}
