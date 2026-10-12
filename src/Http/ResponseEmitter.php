<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Http;

use EchoFusion\Framework\Exceptions\FrameworkException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use const CONNECTION_NORMAL;
use function connection_status;
use function header;
use function headers_sent;
use function sprintf;
use function strtolower;

/**
 * Sends a PSR-7 response to the client: status line, headers, then the body in chunks.
 */
final class ResponseEmitter
{
    private const CHUNK_SIZE = 8192;

    /**
     * @throws FrameworkException when output has already started, so the headers can no longer be sent
     */
    public function emit(ResponseInterface $response, ?ServerRequestInterface $request = null): void
    {
        if (headers_sent($file, $line)) {
            throw new FrameworkException(sprintf('The response cannot be sent: output already started in %s:%d.', $file, $line));
        }

        foreach ($response->getHeaders() as $name => $values) {
            // several Set-Cookie headers must all be sent, so only the first one of a name replaces
            $replace = strtolower((string) $name) !== 'set-cookie';
            foreach ($values as $value) {
                header(sprintf('%s: %s', $name, $value), $replace);
                $replace = false;
            }
        }

        header(sprintf('HTTP/%s %d %s', $response->getProtocolVersion(), $response->getStatusCode(), $response->getReasonPhrase()), true, $response->getStatusCode());

        $status = $response->getStatusCode();
        $headOnly = $request !== null && strtolower($request->getMethod()) === 'head';
        if ($headOnly || $status === 204 || $status === 304 || ($status >= 100 && $status < 200)) {
            return;
        }

        $body = $response->getBody();
        if ($body->isSeekable()) {
            $body->rewind();
        }

        while (!$body->eof()) {
            echo $body->read(self::CHUNK_SIZE);

            if (connection_status() !== CONNECTION_NORMAL) {
                break;
            }
        }
    }
}
