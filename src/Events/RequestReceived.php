<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Events;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Dispatched before the request enters the middleware. A listener may replace `$request`.
 */
final class RequestReceived
{
    public function __construct(public ServerRequestInterface $request)
    {
    }
}
