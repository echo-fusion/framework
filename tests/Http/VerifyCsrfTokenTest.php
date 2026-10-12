<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Tests\Http;

use EchoFusion\Framework\Exceptions\HttpException;
use EchoFusion\Framework\Http\Middleware\VerifyCsrfToken;
use EchoFusion\SessionManager\ArraySession;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class VerifyCsrfTokenTest extends TestCase
{
    private ArraySession $session;

    private object $handler;

    protected function setUp(): void
    {
        $this->session = new ArraySession();
        $this->handler = new class() implements RequestHandlerInterface {
            public ?ServerRequestInterface $received = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->received = $request;

                return new Response(200, [], 'handled');
            }
        };
    }

    /**
     * @param list<string> $except
     */
    private function middleware(array $except = []): VerifyCsrfToken
    {
        return new VerifyCsrfToken($this->session, $except);
    }

    private function process(VerifyCsrfToken $middleware, ServerRequest $request): ResponseInterface
    {
        return $middleware->process($request, $this->handler);
    }

    public function testASessionGetsOneTokenThatIsReusedAndPassedToTheRequest(): void
    {
        $middleware = $this->middleware();

        $this->process($middleware, new ServerRequest('GET', '/'));
        $first = $this->handler->received?->getAttribute('csrf_token');
        $this->process($middleware, new ServerRequest('GET', '/'));

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $first);
        $this->assertSame($first, $this->handler->received?->getAttribute('csrf_token'));
        $this->assertSame($first, $this->session->get(VerifyCsrfToken::SESSION_KEY));
        $this->assertTrue($this->session->isActive(), 'the middleware starts the session');
    }

    public function testASessionThatIsAlreadyStartedIsUsedAsItIs(): void
    {
        $this->session->start();
        $this->session->put(VerifyCsrfToken::SESSION_KEY, 'existing-token');

        $this->process($this->middleware(), new ServerRequest('GET', '/'));

        $this->assertSame('existing-token', $this->handler->received?->getAttribute('csrf_token'));
    }

    /**
     * @dataProvider safeMethods
     */
    public function testReadingNeedsNoToken(string $method): void
    {
        $response = $this->process($this->middleware(), new ServerRequest($method, '/'));

        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * @return array<string, array{string}>
     */
    public function safeMethods(): array
    {
        return ['GET' => ['GET'], 'HEAD' => ['HEAD'], 'OPTIONS' => ['OPTIONS'], 'lower case' => ['get']];
    }

    /**
     * @dataProvider changingMethods
     */
    public function testChangingRequestsWithoutAValidTokenAreRejected(string $method): void
    {
        $this->session->start();
        $this->session->put(VerifyCsrfToken::SESSION_KEY, 'secret-token');

        foreach ([
            new ServerRequest($method, '/'),
            (new ServerRequest($method, '/'))->withParsedBody(['_token' => 'wrong']),
            (new ServerRequest($method, '/'))->withParsedBody(['other' => 'secret-token']),
            (new ServerRequest($method, '/'))->withParsedBody(['_token' => '']),
            (new ServerRequest($method, '/'))->withHeader('X-CSRF-TOKEN', 'wrong'),
            (new ServerRequest($method, '/'))->withParsedBody(['_token' => ['secret-token']]),
        ] as $request) {
            try {
                $this->process($this->middleware(), $request);
                $this->fail('Expected HttpException for ' . $method);
            } catch (HttpException $e) {
                $this->assertSame(419, $e->getStatusCode());
            }
        }

        $this->assertNull($this->handler->received, 'the action never ran');
    }

    /**
     * @return array<string, array{string}>
     */
    public function changingMethods(): array
    {
        return ['POST' => ['POST'], 'PUT' => ['PUT'], 'PATCH' => ['PATCH'], 'DELETE' => ['DELETE']];
    }

    public function testTheTokenCanBeSentAsAFieldAHeaderOrInJson(): void
    {
        $this->session->start();
        $this->session->put(VerifyCsrfToken::SESSION_KEY, 'secret-token');
        $middleware = $this->middleware();

        foreach ([
            (new ServerRequest('POST', '/'))->withParsedBody(['_token' => 'secret-token']),
            (new ServerRequest('POST', '/'))->withHeader('X-CSRF-TOKEN', 'secret-token'),
            new ServerRequest('PUT', '/', ['Content-Type' => 'application/json'], '{"_token":"secret-token","a":1}'),
            new ServerRequest('DELETE', '/', ['Content-Type' => 'application/x-www-form-urlencoded'], '_token=secret-token'),
        ] as $request) {
            $this->assertSame(200, $this->process($middleware, $request)->getStatusCode());
        }
    }

    public function testExceptedPathsNeedNoToken(): void
    {
        $middleware = $this->middleware(['/webhook', '/api/*']);

        $this->assertSame(200, $this->process($middleware, new ServerRequest('POST', '/webhook'))->getStatusCode());
        $this->assertSame(200, $this->process($middleware, new ServerRequest('POST', '/api/posts/5'))->getStatusCode());

        $this->expectException(HttpException::class);
        $this->process($middleware, new ServerRequest('POST', '/apix'));
    }

    public function testACustomFieldName(): void
    {
        $this->session->start();
        $this->session->put(VerifyCsrfToken::SESSION_KEY, 'secret-token');
        $middleware = new VerifyCsrfToken($this->session, [], 'csrf');

        $this->assertSame(200, $this->process($middleware, (new ServerRequest('POST', '/'))->withParsedBody(['csrf' => 'secret-token']))->getStatusCode());
    }
}
