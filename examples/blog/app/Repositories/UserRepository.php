<?php

declare(strict_types=1);

namespace App\Repositories;

use EchoFusion\Contracts\DatabaseManager\ConnectionInterface;

final class UserRepository
{
    public const ADMIN = 'admin';

    public const USER = 'user';

    public function __construct(private readonly ConnectionInterface $db)
    {
    }

    public function emailTaken(string $email): bool
    {
        return $this->db->fetchValue('SELECT COUNT(*) FROM users WHERE email = ?', [$email]) > 0;
    }

    /**
     * @return list<array<string, mixed>> without the password and token columns
     */
    public function all(): array
    {
        return $this->db->fetchAll('SELECT u.id, u.name, u.email, u.role, u.created_at, (SELECT COUNT(*) FROM posts p WHERE p.user_id = u.id) AS posts FROM users u ORDER BY u.id');
    }

    public function find(int $id): ?array
    {
        return $this->db->fetchOne('SELECT id, name, email, role, created_at FROM users WHERE id = ?', [$id]);
    }

    public function count(): int
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM users');
    }

    public function admins(): int
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM users WHERE role = ?', [self::ADMIN]);
    }

    public function setRole(int $id, string $role): void
    {
        $this->db->execute('UPDATE users SET role = ? WHERE id = ?', [$role, $id]);
    }

    /**
     * Removes the user and their posts. The posts are deleted explicitly, in one transaction, because SQLite
     * ignores `ON DELETE CASCADE` unless foreign keys are switched on for the connection.
     */
    public function delete(int $id): void
    {
        $this->db->transactional(static function (ConnectionInterface $db) use ($id): void {
            $db->execute('DELETE FROM posts WHERE user_id = ?', [$id]);
            $db->execute('DELETE FROM users WHERE id = ?', [$id]);
        });
    }

    /**
     * Only the SHA-256 hash is stored; the token itself is shown to the user once.
     */
    public function storeToken(int|string $userId, string $token): void
    {
        $this->db->execute('UPDATE users SET api_token = ? WHERE id = ?', [hash('sha256', $token), $userId]);
    }

    public function hasToken(int|string $userId): bool
    {
        return $this->db->fetchValue('SELECT api_token FROM users WHERE id = ?', [$userId]) !== null;
    }
}
