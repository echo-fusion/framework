<?php

declare(strict_types=1);

namespace App\Repositories;

use EchoFusion\Contracts\DatabaseManager\ConnectionInterface;

final class UserRepository
{
    public function __construct(private readonly ConnectionInterface $db)
    {
    }

    public function emailTaken(string $email): bool
    {
        return $this->db->fetchValue('SELECT COUNT(*) FROM users WHERE email = ?', [$email]) > 0;
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
