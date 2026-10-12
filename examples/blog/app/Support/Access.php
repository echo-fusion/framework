<?php

declare(strict_types=1);

namespace App\Support;

use App\Repositories\UserRepository;
use EchoFusion\AuthManager\Users\GenericUser;
use EchoFusion\Contracts\AuthManager\UserInterface;

/**
 * Who may do what. Authentication says who the user is; this says what that user may change.
 */
final class Access
{
    public function isAdmin(?UserInterface $user): bool
    {
        return $user instanceof GenericUser && $user->get('role') === UserRepository::ADMIN;
    }

    /**
     * The author and the admins may change a post.
     *
     * @param array<string, mixed> $post
     */
    public function canManage(?UserInterface $user, array $post): bool
    {
        return $user !== null && ($this->isAdmin($user) || (string) $post['user_id'] === (string) $user->getAuthIdentifier());
    }
}
