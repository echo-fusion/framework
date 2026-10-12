<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Http;

use GuzzleHttp\Psr7\ServerRequest;
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Builds the request of the current PHP request from the superglobals and `php://input`.
 *
 * It is a service so a test, or an application behind an unusual server, can replace it.
 */
class GlobalsRequestFactory
{
    /**
     * @throws InvalidArgumentException when the server handed over something PSR-7 refuses (for example a malformed header name)
     */
    public function create(): ServerRequestInterface
    {
        return ServerRequest::fromGlobals();
    }
}
