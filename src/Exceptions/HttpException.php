<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Exceptions;

use Throwable;

/**
 * Throw it from a controller or a middleware to answer with a specific HTTP status:
 * `throw new HttpException(403, 'You cannot edit this post.')`.
 */
class HttpException extends FrameworkException
{
    /**
     * @param array<string, string|list<string>> $headers
     */
    public function __construct(
        private readonly int $statusCode,
        string $message = '',
        private readonly array $headers = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, string|list<string>>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }
}
