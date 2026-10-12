<?php

declare(strict_types=1);

namespace App\Repositories;

use EchoFusion\Contracts\DatabaseManager\ConnectionInterface;

/**
 * All SQL about posts lives here; controllers never write queries.
 *
 * The `$onlyPublished` and `$userId` arguments narrow a list: the public pages show published posts,
 * "my posts" shows one author's posts including drafts, the admin panel shows everything.
 */
final class PostRepository
{
    public const PUBLISHED = 'published';

    public const DRAFT = 'draft';

    public function __construct(private readonly ConnectionInterface $db)
    {
    }

    /**
     * @return list<array<string, mixed>> newest first, with the author's name
     */
    public function latest(int $limit = 20, int $offset = 0, bool $onlyPublished = true, ?int $userId = null, string $search = ''): array
    {
        [$where, $params] = $this->filter($onlyPublished, $userId, $search);

        return $this->db->fetchAll(
            'SELECT p.*, u.name AS author FROM posts p JOIN users u ON u.id = p.user_id' . $where . ' ORDER BY p.id DESC LIMIT ? OFFSET ?',
            [...$params, $limit, $offset],
        );
    }

    public function count(bool $onlyPublished = true, ?int $userId = null, string $search = ''): int
    {
        [$where, $params] = $this->filter($onlyPublished, $userId, $search);

        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM posts p' . $where, $params);
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

    public function create(int $userId, string $title, string $body, string $status = self::PUBLISHED): int
    {
        $this->db->execute('INSERT INTO posts (user_id, title, body, status) VALUES (?, ?, ?, ?)', [$userId, $title, $body, $status]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, string $title, string $body, ?string $status = null): void
    {
        $this->db->execute(
            'UPDATE posts SET title = ?, body = ?, status = COALESCE(?, status), updated_at = CURRENT_TIMESTAMP WHERE id = ?',
            [$title, $body, $status, $id],
        );
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM posts WHERE id = ?', [$id]);
    }

    /**
     * @return array{total: int, published: int, drafts: int}
     */
    public function stats(): array
    {
        $row = $this->db->fetchOne('SELECT COUNT(*) AS total, COALESCE(SUM(status = ?), 0) AS published, COALESCE(SUM(status = ?), 0) AS drafts FROM posts', [self::PUBLISHED, self::DRAFT]);

        return ['total' => (int) ($row['total'] ?? 0), 'published' => (int) ($row['published'] ?? 0), 'drafts' => (int) ($row['drafts'] ?? 0)];
    }

    /**
     * @return array{0: string, 1: list<mixed>} the WHERE clause and its parameters
     */
    private function filter(bool $onlyPublished, ?int $userId, string $search): array
    {
        $conditions = [];
        $params = [];

        if ($onlyPublished) {
            $conditions[] = 'p.status = ?';
            $params[] = self::PUBLISHED;
        }
        if ($userId !== null) {
            $conditions[] = 'p.user_id = ?';
            $params[] = $userId;
        }
        if ($search !== '') {
            $conditions[] = '(p.title LIKE ? OR p.body LIKE ?)';
            $like = '%' . addcslashes($search, '%_\\') . '%';
            array_push($params, $like, $like);
        }

        return [$conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions), $params];
    }
}
