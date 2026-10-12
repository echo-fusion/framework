<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Access;
use EchoFusion\Contracts\AuthManager\UserInterface;
use EchoFusion\Framework\Exceptions\HttpException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Only admins pass. Use it after `auth`, which puts the user on the request: ['auth', 'admin'].
 */
final class RequireAdmin implements MiddlewareInterface
{
    public function __construct(private readonly Access $access)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $request->getAttribute('auth.user');

        if (!$user instanceof UserInterface || !$this->access->isAdmin($user)) {
            throw new HttpException(403, 'This area is for administrators.');
        }

        return $handler->handle($request);
    }
}
