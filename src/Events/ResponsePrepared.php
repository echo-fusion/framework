<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Events;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Dispatched with the final response, whether it came from the application or from the error handler.
 * A listener may replace `$response` (add a header, compress the body ...).
 */
final class ResponsePrepared
{
    public function __construct(public readonly ServerRequestInterface $request, public ResponseInterface $response)
    {
    }
}
