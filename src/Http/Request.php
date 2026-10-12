<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Http;

use EchoFusion\Contracts\AuthManager\UserInterface;
use EchoFusion\Framework\Exceptions\HttpException;
use EchoFusion\Framework\Support\Arr;
use JsonException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use const FILTER_NULL_ON_FAILURE;
use const FILTER_VALIDATE_BOOLEAN;
use const JSON_THROW_ON_ERROR;
use const UPLOAD_ERR_OK;
use function array_replace;
use function filter_var;
use function is_array;
use function is_numeric;
use function is_string;
use function json_decode;
use function parse_str;
use function preg_match;
use function str_contains;
use function strtolower;
use function strtoupper;
use function trim;

/**
 * The request as a controller wants to read it. Ask for it as an action argument:
 *
 * <code>
 * public function store(Request $request): array
 * {
 *     $title = $request->input('title');            // from the form, the JSON body or the query string
 *     $page = $request->query('page', 1);           // only ?page=
 *     $data = $request->only('title', 'body');
 *     $request->json('tags.0');                     // only the JSON body, with dot keys
 * }
 * </code>
 *
 * It wraps the PSR-7 request ({@see psr()}), which stays the source of truth. Extend this class to make your own
 * request type (for example one with a `validated()` method) and type-hint that in the action instead.
 */
class Request
{
    /**
     * @var array<array-key, mixed>|null
     */
    private ?array $json = null;

    /**
     * @var array<array-key, mixed>|null
     */
    private ?array $form = null;

    public function __construct(private readonly ServerRequestInterface $request)
    {
    }

    /**
     * The underlying PSR-7 request.
     */
    public function psr(): ServerRequestInterface
    {
        return $this->request;
    }

    // ------------------------------------------------------------------ the request line

    public function method(): string
    {
        return strtoupper($this->request->getMethod());
    }

    public function isMethod(string ...$methods): bool
    {
        foreach ($methods as $method) {
            if (strtoupper($method) === $this->method()) {
                return true;
            }
        }

        return false;
    }

    /**
     * The path without the query string, e.g. `/posts/5`.
     */
    public function path(): string
    {
        $path = $this->request->getUri()->getPath();

        return $path === '' ? '/' : $path;
    }

    public function uri(): string
    {
        return (string) $this->request->getUri();
    }

    // ------------------------------------------------------------------ input

    /**
     * The query string (`?page=2`); with a key, one value (dot keys reach into `?filter[status]=open`).
     */
    public function query(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->request->getQueryParams() : Arr::get($this->request->getQueryParams(), $key, $default);
    }

    /**
     * The fields of a submitted form (not JSON); with a key, one field.
     */
    public function post(?string $key = null, mixed $default = null): mixed
    {
        $form = $this->form();

        return $key === null ? $form : Arr::get($form, $key, $default);
    }

    /**
     * The JSON body as an array; with a key (dot keys allowed), one value. Without a JSON body: the default.
     *
     * @throws HttpException 400 when the body says it is JSON but is not valid JSON
     */
    public function json(?string $key = null, mixed $default = null): mixed
    {
        $json = $this->decodedJson();

        return $key === null ? $json : Arr::get($json, $key, $default);
    }

    /**
     * Everything the client sent as data: the query string, overridden by the form fields or the JSON body.
     * This is what you usually want for "the value of the field `title`".
     */
    public function input(?string $key = null, mixed $default = null): mixed
    {
        $all = $this->all();

        return $key === null ? $all : Arr::get($all, $key, $default);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function all(): array
    {
        return array_replace($this->request->getQueryParams(), $this->isJson() ? $this->decodedJson() : $this->form());
    }

    public function has(string ...$keys): bool
    {
        $all = $this->all();
        foreach ($keys as $key) {
            if (!Arr::has($all, $key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether the input has the key and its value is not empty text (`''`) or `null`.
     */
    public function filled(string $key): bool
    {
        $value = $this->input($key);

        return $value !== null && (!is_string($value) || trim($value) !== '');
    }

    /**
     * @return array<array-key, mixed>
     */
    public function only(string ...$keys): array
    {
        return Arr::only($this->all(), $keys);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function except(string ...$keys): array
    {
        return Arr::except($this->all(), $keys);
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->input($key);

        return is_string($value) || is_numeric($value) ? (string) $value : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->input($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function float(string $key, float $default = 0.0): float
    {
        $value = $this->input($key);

        return is_numeric($value) ? (float) $value : $default;
    }

    /**
     * "1", "true", "on" and "yes" are true; "0", "false", "off" and "no" are false (checkboxes send "on").
     */
    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->input($key);

        if (is_string($value)) {
            $value = strtolower(trim($value));
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    // ------------------------------------------------------------------ files, headers, cookies

    /**
     * @return array<array-key, mixed> the uploaded files (nested like the form fields)
     */
    public function files(): array
    {
        return $this->request->getUploadedFiles();
    }

    public function file(string $key): ?UploadedFileInterface
    {
        $file = Arr::get($this->request->getUploadedFiles(), $key);

        return $file instanceof UploadedFileInterface ? $file : null;
    }

    public function hasFile(string $key): bool
    {
        return $this->file($key)?->getError() === UPLOAD_ERR_OK;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->request->hasHeader($name) ? $this->request->getHeaderLine($name) : $default;
    }

    public function bearerToken(): ?string
    {
        return preg_match('/^Bearer\s+(\S+)$/i', $this->request->getHeaderLine('Authorization'), $matches) === 1 ? $matches[1] : null;
    }

    public function cookie(string $name, ?string $default = null): ?string
    {
        $value = $this->request->getCookieParams()[$name] ?? null;

        return is_string($value) ? $value : $default;
    }

    // ------------------------------------------------------------------ what kind of request

    public function isJson(): bool
    {
        return str_contains(strtolower($this->request->getHeaderLine('Content-Type')), 'json');
    }

    /**
     * Whether the client asks for JSON (`Accept: application/json`) or is an AJAX call.
     */
    public function wantsJson(): bool
    {
        return str_contains(strtolower($this->request->getHeaderLine('Accept')), 'json') || $this->isAjax();
    }

    public function isAjax(): bool
    {
        return strtolower($this->request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest';
    }

    /**
     * The address PHP saw the connection come from. Behind a proxy this is the proxy: read the header your proxy sets.
     */
    public function ip(): ?string
    {
        $ip = $this->request->getServerParams()['REMOTE_ADDR'] ?? null;

        return is_string($ip) ? $ip : null;
    }

    public function userAgent(): ?string
    {
        return $this->header('User-Agent');
    }

    // ------------------------------------------------------------------ set by the framework and middleware

    /**
     * A request attribute: a route placeholder (`$request->attribute('id')`), or something middleware added.
     */
    public function attribute(string $name, mixed $default = null): mixed
    {
        return $this->request->getAttribute($name, $default);
    }

    /**
     * The authenticated user, when the `auth` middleware ran for this route.
     */
    public function user(): ?UserInterface
    {
        $user = $this->request->getAttribute('auth.user');

        return $user instanceof UserInterface ? $user : null;
    }

    /**
     * The CSRF token of this session (when the CSRF middleware is on), to put in a form as `_token`.
     */
    public function csrfToken(): ?string
    {
        $token = $this->request->getAttribute('csrf_token');

        return is_string($token) ? $token : null;
    }

    // ------------------------------------------------------------------ parsing

    /**
     * @return array<array-key, mixed>
     */
    private function form(): array
    {
        if ($this->form !== null) {
            return $this->form;
        }

        $parsed = $this->request->getParsedBody();
        if (is_array($parsed) && $parsed !== []) {
            return $this->form = $parsed;
        }

        // PHP only fills $_POST for POST, and gives an empty array for PUT, PATCH and DELETE: read their forms from the body
        if (str_contains(strtolower($this->request->getHeaderLine('Content-Type')), 'application/x-www-form-urlencoded')) {
            parse_str($this->body(), $fields);

            return $this->form = $fields;
        }

        return $this->form = [];
    }

    /**
     * @return array<array-key, mixed>
     */
    private function decodedJson(): array
    {
        if ($this->json !== null) {
            return $this->json;
        }

        if (!$this->isJson()) {
            return $this->json = [];
        }

        $body = $this->body();
        if (trim($body) === '') {
            return $this->json = [];
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new HttpException(400, 'Malformed JSON body.', [], $e);
        }

        // a JSON body that is a bare value ("5", "text") has no keys to read
        return $this->json = is_array($decoded) ? $decoded : [];
    }

    private function body(): string
    {
        $stream = $this->request->getBody();
        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        $contents = $stream->getContents();
        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        return $contents;
    }
}
