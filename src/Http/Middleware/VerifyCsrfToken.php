<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Http\Middleware;

use EchoFusion\Contracts\SessionManager\SessionInterface;
use EchoFusion\Framework\Exceptions\HttpException;
use EchoFusion\Framework\Http\Request;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use function bin2hex;
use function fnmatch;
use function hash_equals;
use function in_array;
use function is_string;
use function random_bytes;
use function strtoupper;

/**
 * Protects forms against cross-site request forgery.
 *
 * Every session gets a random token. A request that changes something (anything but GET, HEAD and OPTIONS) must send
 * it back as the form field `_token` or the header `X-CSRF-TOKEN`; without it the answer is `419 Page Expired`.
 * A page on another site cannot read the token, so it cannot forge a request that the browser would send with your cookie.
 *
 * Put the token in a form with `$request->csrfToken()`. Paths listed in `except` are skipped, patterns like `/api/*`
 * work: APIs authenticated by a token in a header are not exposed to this attack, because browsers do not add that
 * header by themselves.
 */
final class VerifyCsrfToken implements MiddlewareInterface
{
    public const SESSION_KEY = '_csrf';

    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    /**
     * @param list<string> $except paths that need no token; `*` matches any text
     */
    public function __construct(
        private readonly SessionInterface $session,
        private readonly array $except = [],
        private readonly string $field = '_token',
        private readonly string $header = 'X-CSRF-TOKEN',
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->session->isActive()) {
            $this->session->start();
        }

        $token = $this->session->get(self::SESSION_KEY);
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->session->put(self::SESSION_KEY, $token);
        }

        $request = $request->withAttribute('csrf_token', $token);

        if (!in_array(strtoupper($request->getMethod()), self::SAFE_METHODS, true) && !$this->isExcepted($request)) {
            $sent = $this->sentToken($request);

            if ($sent === null || !hash_equals($token, $sent)) {
                throw new HttpException(419, 'The page expired. Reload it and try again.');
            }
        }

        return $handler->handle($request);
    }

    private function sentToken(ServerRequestInterface $request): ?string
    {
        $header = $request->getHeaderLine($this->header);
        if ($header !== '') {
            return $header;
        }

        $field = (new Request($request))->input($this->field);

        return is_string($field) && $field !== '' ? $field : null;
    }

    private function isExcepted(ServerRequestInterface $request): bool
    {
        $path = $request->getUri()->getPath();

        foreach ($this->except as $pattern) {
            if ($pattern === $path || fnmatch($pattern, $path)) {
                return true;
            }
        }

        return false;
    }
}
