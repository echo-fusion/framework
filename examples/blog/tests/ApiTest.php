<?php

declare(strict_types=1);

namespace Tests;

use EchoFusion\Contracts\DatabaseManager\ConnectionInterface;
use EchoFusion\Framework\Database\MigrationRunner;
use GuzzleHttp\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use const JSON_THROW_ON_ERROR;
use function dirname;
use function file_exists;
use function hash;
use function http_build_query;
use function json_decode;
use function json_encode;
use function unlink;

/**
 * The JSON API, end to end: routing, token authentication, `Request` in the controller, the database.
 * Every test starts with a fresh SQLite file that the real migrations have filled.
 */
final class ApiTest extends TestCase
{
    private const ANN = 'ann-token';

    private const BOB = 'bob-token';

    protected function setUp(): void
    {
        $file = dirname(__DIR__) . '/storage/testing.sqlite';
        file_exists($file) && unlink($file);

        $db = $this->app()->getContainer()->get(ConnectionInterface::class);
        (new MigrationRunner($db, dirname(__DIR__) . '/database/migrations'))->migrate();

        foreach ([[1, 'Ann', self::ANN], [2, 'Bob', self::BOB]] as [$id, $name, $token]) {
            $db->execute('INSERT INTO users (id, name, email, password, api_token) VALUES (?, ?, ?, ?, ?)', [$id, $name, strtolower($name) . '@example.com', 'x', hash('sha256', $token)]);
        }
    }

    public function testReadingIsPublic(): void
    {
        $this->assertSame(['data' => [], 'total' => 0], $this->decode($this->api('GET', '/api/posts')));

        $this->api('POST', '/api/posts', ['title' => 'Hello', 'body' => 'World'], self::ANN);
        $list = $this->decode($this->api('GET', '/api/posts?limit=1'));

        $this->assertSame(1, $list['total']);
        $this->assertSame('Hello', $list['data'][0]['title']);
        $this->assertSame('Ann', $list['data'][0]['author']);
        $this->assertSame('World', $this->decode($this->api('GET', '/api/posts/1'))['data']['body']);
        $this->assertSame(404, $this->api('GET', '/api/posts/99')->getStatusCode());
    }

    public function testWritingNeedsAValidToken(): void
    {
        $this->assertSame(401, $this->api('POST', '/api/posts', ['title' => 'a', 'body' => 'b'])->getStatusCode());
        $this->assertSame(401, $this->api('POST', '/api/posts', ['title' => 'a', 'body' => 'b'], 'wrong')->getStatusCode());
        $this->assertSame(401, $this->api('GET', '/api/me')->getStatusCode());
    }

    public function testMeShowsTheTokenOwner(): void
    {
        $me = $this->decode($this->api('GET', '/api/me', null, self::BOB))['data'];

        $this->assertSame(['id' => 2, 'name' => 'Bob', 'email' => 'bob@example.com'], $me);
    }

    public function testCreateValidatesTheJsonBody(): void
    {
        $this->assertSame(422, $this->api('POST', '/api/posts', ['title' => '', 'body' => 'b'], self::ANN)->getStatusCode());
        $this->assertSame(422, $this->api('POST', '/api/posts', ['body' => 'b'], self::ANN)->getStatusCode());
        $this->assertSame(0, $this->decode($this->api('GET', '/api/posts'))['total']);
    }

    public function testPutReplacesAndPatchChangesWhatWasSent(): void
    {
        $this->api('POST', '/api/posts', ['title' => 'One', 'body' => 'First'], self::ANN);

        $put = $this->decode($this->api('PUT', '/api/posts/1', ['title' => 'Two', 'body' => 'Second'], self::ANN))['data'];
        $this->assertSame(['Two', 'Second'], [$put['title'], $put['body']]);

        $patch = $this->decode($this->api('PATCH', '/api/posts/1', ['title' => 'Three'], self::ANN))['data'];
        $this->assertSame(['Three', 'Second'], [$patch['title'], $patch['body']], 'PATCH keeps the text');

        $this->assertSame(422, $this->api('PUT', '/api/posts/1', ['title' => 'Only a title'], self::ANN)->getStatusCode(), 'PUT needs everything');
    }

    public function testPutReadsAFormEncodedBodyToo(): void
    {
        $this->api('POST', '/api/posts', ['title' => 'One', 'body' => 'First'], self::ANN);

        $request = (new ServerRequest('PUT', '/api/posts/1', ['Authorization' => 'Bearer ' . self::ANN, 'Content-Type' => 'application/x-www-form-urlencoded', 'Accept' => 'application/json'], http_build_query(['title' => 'Form', 'body' => 'Encoded'])))->withParsedBody([]);
        $response = $this->app()->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Form', $this->decode($response)['data']['title']);
    }

    public function testOnlyTheOwnerChangesOrDeletesAPost(): void
    {
        $this->api('POST', '/api/posts', ['title' => 'Mine', 'body' => 'Ann wrote it'], self::ANN);

        $this->assertSame(403, $this->api('PUT', '/api/posts/1', ['title' => 'Hijack', 'body' => 'x'], self::BOB)->getStatusCode());
        $this->assertSame(403, $this->api('DELETE', '/api/posts/1', null, self::BOB)->getStatusCode());
        $this->assertSame('Mine', $this->decode($this->api('GET', '/api/posts/1'))['data']['title']);

        $deleted = $this->api('DELETE', '/api/posts/1', null, self::ANN);
        $this->assertSame(204, $deleted->getStatusCode());
        $this->assertSame(404, $this->api('GET', '/api/posts/1')->getStatusCode());
        $this->assertSame(404, $this->api('DELETE', '/api/posts/1', null, self::ANN)->getStatusCode());
    }

    /**
     * @param array<string, mixed>|null $json
     */
    private function api(string $method, string $uri, ?array $json = null, ?string $token = null): ResponseInterface
    {
        $headers = ['Accept' => 'application/json'];
        if ($token !== null) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }
        if ($json !== null) {
            $headers['Content-Type'] = 'application/json';
        }

        return $this->app()->handle(new ServerRequest($method, $uri, $headers, $json === null ? null : json_encode($json)));
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}
