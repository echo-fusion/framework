<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Events;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Dispatched when handling a request throws. A listener may set `$response` to answer in its own way;
 * otherwise the error handler builds the response.
 */
final class ExceptionRaised
{
    public ?ResponseInterface $response = null;

    public function __construct(public readonly Throwable $exception, public readonly ServerRequestInterface $request)
    {
    }
}
