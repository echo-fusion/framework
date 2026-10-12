<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Http;

use EchoFusion\ResponseManager\HtmlResponse;
use EchoFusion\ResponseManager\JsonResponse;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Stringable;
use function is_array;
use function is_object;
use function is_scalar;
use function is_string;

/**
 * Turns what an action returns into a PSR-7 response:
 *
 * - a response stays as it is
 * - text (a string or a Stringable) becomes an HTML response
 * - `null` becomes an empty 204 response
 * - anything else (an array, an object, a number, a boolean) becomes a JSON response
 */
final class ResponseConverter
{
    public function convert(mixed $value): ResponseInterface
    {
        if ($value instanceof ResponseInterface) {
            return $value;
        }

        if ($value === null) {
            return new Response(204);
        }

        if (is_string($value) || $value instanceof Stringable) {
            return new HtmlResponse((string) $value);
        }

        if (is_scalar($value) || is_array($value) || is_object($value)) {
            return new JsonResponse($value);
        }

        return new Response(204);
    }
}
