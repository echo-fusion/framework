<?php

declare(strict_types=1);

namespace App\Repositories;

use EchoFusion\Contracts\DatabaseManager\ConnectionInterface;

/**
 * All SQL about posts lives here; controllers never write queries.
 */
final class PostRepository
{
    public function __construct(private readonly ConnectionInterface $db)
    {
    }

    /**
     * @return list<array<string, mixed>> newest first, with the author's name
     */
    public function latest(int $limit = 20, int $offset = 0): array
    {
        return $this->db->fetchAll(
            'SELECT p.*, u.name AS author FROM posts p JOIN users u ON u.id = p.user_id ORDER BY p.id DESC LIMIT ? OFFSET ?',
            [$limit, $offset],
        );
    }

    public function count(): int
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM posts');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT p.*, u.name AS author FROM posts p JOIN users u ON u.id = p.user_id WHERE p.id = ?',
            [$id],
        );
    }

    public function create(int $userId, string $title, string $body): int
    {
        $this->db->execute('INSERT INTO posts (user_id, title, body) VALUES (?, ?, ?)', [$userId, $title, $body]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, string $title, string $body): void
    {
        $this->db->execute('UPDATE posts SET title = ?, body = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?', [$title, $body, $id]);
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM posts WHERE id = ?', [$id]);
    }
}
