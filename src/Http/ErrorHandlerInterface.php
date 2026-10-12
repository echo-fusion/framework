<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Turns whatever was thrown while handling a request into a response. Bind your own implementation to
 * this interface in the container to change how errors look.
 */
interface ErrorHandlerInterface
{
    public function handle(Throwable $exception, ServerRequestInterface $request): ResponseInterface;
}
