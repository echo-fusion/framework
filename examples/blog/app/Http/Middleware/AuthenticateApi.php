<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use EchoFusion\AuthManager\Middleware\AuthenticateMiddleware;
use EchoFusion\Contracts\AuthManager\AuthManagerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The same authentication as the `auth` middleware, but with the token guard `api`
 * (the `auth` alias uses the session guard `web`).
 */
final class AuthenticateApi implements MiddlewareInterface
{
    private readonly AuthenticateMiddleware $inner;

    public function __construct(AuthManagerInterface $auth, ResponseFactoryInterface $responses)
    {
        $this->inner = new AuthenticateMiddleware($auth, $responses, 'api');
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $this->inner->process($request, $handler);
    }
}
