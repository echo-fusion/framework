<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Tests\Http;

use EchoFusion\Framework\Exceptions\HttpException;
use EchoFusion\Framework\Http\Request;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\UploadedFile;
use PHPUnit\Framework\TestCase;
use const UPLOAD_ERR_NO_FILE;
use const UPLOAD_ERR_OK;

final class RequestTest extends TestCase
{
    /**
     * @param array<string, string|list<string>> $headers
     */
    private function request(string $method = 'GET', string $uri = '/', array $headers = [], ?string $body = null): ServerRequest
    {
        return new ServerRequest($method, $uri, $headers, $body);
    }

    public function testTheRequestLine(): void
    {
        $request = new Request($this->request('post', '/posts/5?x=1'));

        $this->assertSame('POST', $request->method());
        $this->assertTrue($request->isMethod('get', 'post'));
        $this->assertFalse($request->isMethod('delete'));
        $this->assertSame('/posts/5', $request->path());
        $this->assertSame('/posts/5?x=1', $request->uri());
        $this->assertSame('/', (new Request($this->request('GET', 'http://localhost')))->path());
    }

    public function testQueryStringWithDotKeys(): void
    {
        $psr = $this->request('GET', '/?page=2&filter[status]=open')->withQueryParams(['page' => '2', 'filter' => ['status' => 'open']]);
        $request = new Request($psr);

        $this->assertSame('2', $request->query('page'));
        $this->assertSame(1, $request->query('missing', 1));
        $this->assertSame('open', $request->query('filter.status'));
        $this->assertSame(['page' => '2', 'filter' => ['status' => 'open']], $request->query());
    }

    public function testPostFieldsOfAForm(): void
    {
        $psr = $this->request('POST', '/', ['Content-Type' => 'application/x-www-form-urlencoded'])->withParsedBody(['title' => 'Hi', 'tags' => ['a', 'b']]);
        $request = new Request($psr);

        $this->assertSame('Hi', $request->post('title'));
        $this->assertSame('b', $request->post('tags.1'));
        $this->assertSame('d', $request->post('missing', 'd'));
        $this->assertSame(['title' => 'Hi', 'tags' => ['a', 'b']], $request->post());
        $this->assertSame([], $request->json(), 'a form is not JSON');
    }

    public function testFormBodiesOfPutPatchAndDeleteAreReadFromTheBody(): void
    {
        // the real front controller passes $_POST, which PHP leaves as an empty array for these methods
        foreach (['PUT', 'PATCH', 'DELETE'] as $method) {
            $viaGlobals = new Request($this->request($method, '/', ['Content-Type' => 'application/x-www-form-urlencoded'], 'title=From+globals&x=1')->withParsedBody([]));
            $this->assertSame('From globals', $viaGlobals->post('title'), $method);
            $this->assertSame('From globals', $viaGlobals->input('title'), $method);
        }

        foreach (['PUT', 'PATCH', 'DELETE'] as $method) {
            $request = new Request($this->request($method, '/', ['Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8'], 'title=New+title&tags%5B%5D=a&tags%5B%5D=b'));

            $this->assertSame('New title', $request->post('title'), $method);
            $this->assertSame(['a', 'b'], $request->input('tags'), $method);
        }
    }

    public function testAJsonBody(): void
    {
        $request = new Request($this->request('POST', '/', ['Content-Type' => 'application/json'], '{"title":"Hi","tags":["a","b"],"author":{"name":"Ann"}}'));

        $this->assertTrue($request->isJson());
        $this->assertSame('Hi', $request->json('title'));
        $this->assertSame('b', $request->json('tags.1'));
        $this->assertSame('Ann', $request->json('author.name'));
        $this->assertSame('d', $request->json('missing', 'd'));
        $this->assertSame('Hi', $request->input('title'), 'input() reads JSON too');
        $this->assertSame([], $request->post(), 'post() is the form only');
    }

    public function testJsonVariantsAndOddBodies(): void
    {
        $this->assertTrue((new Request($this->request('POST', '/', ['Content-Type' => 'application/vnd.api+json'])))->isJson());
        $this->assertSame([], (new Request($this->request('POST', '/', ['Content-Type' => 'application/json'], '')))->json());
        $this->assertSame([], (new Request($this->request('POST', '/', ['Content-Type' => 'application/json'], '  ')))->json());
        $this->assertSame([], (new Request($this->request('POST', '/', ['Content-Type' => 'application/json'], '5')))->json(), 'a bare value has no keys');
        $this->assertSame([], (new Request($this->request('POST', '/', [], '{"a":1}')))->json(), 'without the JSON header the body is not read as JSON');
    }

    public function testMalformedJsonIsA400(): void
    {
        $request = new Request($this->request('POST', '/', ['Content-Type' => 'application/json'], '{"title":'));

        try {
            $request->json();
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(400, $e->getStatusCode());
            $this->assertSame('Malformed JSON body.', $e->getMessage());
        }
    }

    public function testTheBodyCanBeReadMoreThanOnce(): void
    {
        $psr = $this->request('POST', '/', ['Content-Type' => 'application/json'], '{"a":1}');
        (new Request($psr))->json();

        $this->assertSame('{"a":1}', (string) $psr->getBody(), 'the stream is rewound for the next reader');
        $this->assertSame(1, (new Request($psr))->json('a'));
    }

    public function testInputMergesTheQueryUnderTheBody(): void
    {
        $psr = $this->request('POST', '/?page=2&title=from-query', ['Content-Type' => 'application/x-www-form-urlencoded'])
            ->withQueryParams(['page' => '2', 'title' => 'from-query'])
            ->withParsedBody(['title' => 'from-form']);
        $request = new Request($psr);

        $this->assertSame('from-form', $request->input('title'), 'the body wins');
        $this->assertSame('2', $request->input('page'), 'the query is still there');
        $this->assertSame(['page' => '2', 'title' => 'from-form'], $request->all());
        $this->assertSame('d', $request->input('missing', 'd'));
    }

    public function testHasFilledOnlyAndExcept(): void
    {
        $psr = $this->request('POST')->withParsedBody(['title' => 'Hi', 'empty' => '  ', 'zero' => '0', 'nothing' => null, 'user' => ['name' => 'Ann', 'age' => '3']]);
        $request = new Request($psr);

        $this->assertTrue($request->has('title'));
        $this->assertTrue($request->has('title', 'user.name'));
        $this->assertFalse($request->has('title', 'missing'));
        $this->assertTrue($request->has('nothing'), 'a key with a null value exists');

        $this->assertTrue($request->filled('title'));
        $this->assertTrue($request->filled('zero'), '"0" is a value');
        $this->assertFalse($request->filled('empty'));
        $this->assertFalse($request->filled('nothing'));
        $this->assertFalse($request->filled('missing'));

        $this->assertSame(['title' => 'Hi', 'user' => ['name' => 'Ann']], $request->only('title', 'user.name', 'missing'));
        $this->assertSame(['title' => 'Hi', 'empty' => '  ', 'zero' => '0', 'nothing' => null, 'user' => ['name' => 'Ann']], $request->except('user.age'));
    }

    public function testTypedGetters(): void
    {
        $psr = $this->request('POST')->withParsedBody(['n' => '42', 'f' => '1.5', 'text' => 'abc', 'on' => 'on', 'off' => 'off', 'yes' => 'Yes', 'zero' => '0', 'junk' => 'maybe', 'list' => ['x']]);
        $request = new Request($psr);

        $this->assertSame(42, $request->int('n'));
        $this->assertSame(7, $request->int('text', 7));
        $this->assertSame(0, $request->int('missing'));
        $this->assertSame(1.5, $request->float('f'));
        $this->assertSame(2.5, $request->float('text', 2.5));
        $this->assertSame('abc', $request->string('text'));
        $this->assertSame('42', $request->string('n'));
        $this->assertSame('d', $request->string('list', 'd'), 'an array is not a string');
        $this->assertTrue($request->bool('on'));
        $this->assertTrue($request->bool('yes'));
        $this->assertFalse($request->bool('off'));
        $this->assertFalse($request->bool('zero'));
        $this->assertTrue($request->bool('junk', true));
        $this->assertFalse($request->bool('missing'));
    }

    public function testFiles(): void
    {
        $file = new UploadedFile('content', 7, UPLOAD_ERR_OK, 'photo.jpg', 'image/jpeg');
        $failed = new UploadedFile('', 0, UPLOAD_ERR_NO_FILE);
        $request = new Request($this->request('POST')->withUploadedFiles(['photo' => $file, 'docs' => ['cv' => $file], 'empty' => $failed]));

        $this->assertSame($file, $request->file('photo'));
        $this->assertSame($file, $request->file('docs.cv'));
        $this->assertNull($request->file('missing'));
        $this->assertTrue($request->hasFile('photo'));
        $this->assertFalse($request->hasFile('empty'), 'a file field that was left empty');
        $this->assertFalse($request->hasFile('missing'));
        $this->assertCount(3, $request->files());
    }

    public function testHeadersCookiesAndClientInfo(): void
    {
        $psr = (new ServerRequest('GET', '/', ['Authorization' => 'Bearer abc123', 'User-Agent' => 'Chrome', 'X-Requested-With' => 'XMLHttpRequest'], null, '1.1', ['REMOTE_ADDR' => '203.0.113.9']))
            ->withCookieParams(['theme' => 'dark', 'bad' => ['x']]);
        $request = new Request($psr);

        $this->assertSame('Chrome', $request->header('user-agent'));
        $this->assertSame('Chrome', $request->userAgent());
        $this->assertNull($request->header('X-Missing'));
        $this->assertSame('fallback', $request->header('X-Missing', 'fallback'));
        $this->assertSame('abc123', $request->bearerToken());
        $this->assertNull((new Request($this->request('GET', '/', ['Authorization' => 'Basic abc'])))->bearerToken());
        $this->assertSame('dark', $request->cookie('theme'));
        $this->assertSame('d', $request->cookie('bad', 'd'));
        $this->assertNull($request->cookie('missing'));
        $this->assertSame('203.0.113.9', $request->ip());
        $this->assertNull((new Request($this->request()))->ip());
        $this->assertTrue($request->isAjax());
        $this->assertTrue($request->wantsJson());
    }

    public function testWantsJson(): void
    {
        $this->assertTrue((new Request($this->request('GET', '/', ['Accept' => 'text/html, application/json;q=0.9'])))->wantsJson());
        $this->assertFalse((new Request($this->request('GET', '/', ['Accept' => 'text/html'])))->wantsJson());
        $this->assertFalse((new Request($this->request()))->wantsJson());
    }

    public function testAttributesUserAndCsrfToken(): void
    {
        $user = new class() implements \EchoFusion\Contracts\AuthManager\UserInterface {
            public function getAuthIdentifier(): string|int
            {
                return 1;
            }

            public function getAuthPassword(): ?string
            {
                return null;
            }
        };
        $request = new Request($this->request()->withAttribute('id', '9')->withAttribute('auth.user', $user)->withAttribute('csrf_token', 'tok'));

        $this->assertSame('9', $request->attribute('id'));
        $this->assertSame('d', $request->attribute('missing', 'd'));
        $this->assertSame($user, $request->user());
        $this->assertSame('tok', $request->csrfToken());

        $bare = new Request($this->request());
        $this->assertNull($bare->user());
        $this->assertNull($bare->csrfToken());
        $this->assertNull((new Request($this->request()->withAttribute('auth.user', 'not a user')))->user());
    }

    public function testThePsrRequestIsStillThere(): void
    {
        $psr = $this->request();

        $this->assertSame($psr, (new Request($psr))->psr());
    }
}
