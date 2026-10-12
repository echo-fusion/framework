<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Http;

use EchoFusion\Contracts\TemplateManager\TemplateEngineInterface;
use EchoFusion\Framework\Exceptions\HttpException;
use EchoFusion\ResponseManager\HtmlResponse;
use EchoFusion\ResponseManager\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use const ENT_QUOTES;
use const ENT_SUBSTITUTE;
use function array_map;
use function htmlspecialchars;
use function sprintf;
use function str_contains;
use function strtolower;

/**
 * The default error handler.
 *
 * - `HttpException` answers with its own status (404 and 405 come from the router); anything else is a 500.
 * - API clients (`Accept: application/json` or `X-Requested-With: XMLHttpRequest`) get JSON; others get HTML.
 * - HTML comes from the template `errors/<status>` or `errors/error` when those exist, otherwise from a small built-in page.
 * - With `debug` on, the message, class, file and trace of the exception are shown; with it off a 500 only says
 *   "Internal Server Error", so nothing about your code leaks.
 * - Server errors (5xx) are logged when a PSR-3 logger is given.
 */
final class ErrorHandler implements ErrorHandlerInterface
{
    public function __construct(
        private readonly bool $debug = false,
        private readonly ?TemplateEngineInterface $templates = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function handle(Throwable $exception, ServerRequestInterface $request): ResponseInterface
    {
        $status = $exception instanceof HttpException ? $exception->getStatusCode() : 500;
        $headers = $exception instanceof HttpException ? $exception->getHeaders() : [];

        if ($status >= 500) {
            $this->logger?->error($exception->getMessage(), ['exception' => $exception]);
        }

        $message = $this->message($exception, $status);

        if ($this->wantsJson($request)) {
            return new JsonResponse($this->payload($exception, $status, $message), $status, $headers);
        }

        return new HtmlResponse($this->html($exception, $status, $message), $status, $headers);
    }

    private function message(Throwable $exception, int $status): string
    {
        $message = $exception->getMessage();

        // an HttpException's message was written to be shown; for a router 404 the details are only for developers
        if ($message === '' && $this->debug && $exception->getPrevious() !== null) {
            $message = $exception->getPrevious()->getMessage();
        }

        if ($message !== '' && ($exception instanceof HttpException || $this->debug)) {
            return $message;
        }

        // the message of any other exception may reveal internals
        return self::reason($status);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Throwable $exception, int $status, string $message): array
    {
        $payload = ['status' => $status, 'message' => $message];

        if ($this->debug) {
            $payload['exception'] = $exception::class;
            $payload['file'] = $exception->getFile() . ':' . $exception->getLine();
            $payload['trace'] = array_map(
                static fn (array $frame): string => sprintf('%s%s%s() at %s:%s', $frame['class'] ?? '', $frame['type'] ?? '', $frame['function'], $frame['file'] ?? '?', $frame['line'] ?? '?'),
                $exception->getTrace(),
            );
        }

        return $payload;
    }

    private function html(Throwable $exception, int $status, string $message): string
    {
        if ($this->templates !== null) {
            foreach (['errors/' . $status, 'errors/error'] as $template) {
                if ($this->templates->exists($template)) {
                    try {
                        return $this->templates->render($template, [
                            'status' => $status,
                            'message' => $message,
                            'exception' => $this->debug ? $exception : null,
                        ]);
                    } catch (Throwable) {
                        break; // an error page must never throw: use the built-in one
                    }
                }
            }
        }

        $title = htmlspecialchars(sprintf('%d %s', $status, self::reason($status)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $body = $message !== self::reason($status) ? '<p>' . htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>' : '';

        if ($this->debug) {
            $body .= sprintf(
                '<pre>%s in %s:%d%s%s</pre>',
                htmlspecialchars($exception::class, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                htmlspecialchars($exception->getFile(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                $exception->getLine(),
                "\n\n",
                htmlspecialchars($exception->getTraceAsString(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            );
        }

        return sprintf('<!doctype html><html lang="en"><head><meta charset="utf-8"><title>%1$s</title></head><body><h1>%1$s</h1>%2$s</body></html>', $title, $body);
    }

    private function wantsJson(ServerRequestInterface $request): bool
    {
        return str_contains(strtolower($request->getHeaderLine('Accept')), 'json')
            || strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest';
    }

    private static function reason(int $status): string
    {
        return match ($status) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            409 => 'Conflict',
            419 => 'Page Expired',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            503 => 'Service Unavailable',
            default => $status >= 500 ? 'Server Error' : 'Error',
        };
    }
}
