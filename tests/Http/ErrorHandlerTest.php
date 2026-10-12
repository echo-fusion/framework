<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Tests\Http;

use EchoFusion\Contracts\TemplateManager\TemplateEngineInterface;
use EchoFusion\Framework\Exceptions\HttpException;
use EchoFusion\Framework\Http\ErrorHandler;
use EchoFusion\Framework\Http\ErrorHandlerInterface;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;

final class ErrorHandlerTest extends TestCase
{
    private function request(string $accept = ''): ServerRequest
    {
        return new ServerRequest('GET', '/', $accept === '' ? [] : ['Accept' => $accept]);
    }

    public function testItIsAnErrorHandler(): void
    {
        $this->assertInstanceOf(ErrorHandlerInterface::class, new ErrorHandler());
    }

    public function testAnHttpExceptionKeepsItsStatusHeadersAndMessage(): void
    {
        $response = (new ErrorHandler())->handle(new HttpException(429, 'Slow down.', ['Retry-After' => '30']), $this->request());

        $this->assertSame(429, $response->getStatusCode());
        $this->assertSame('30', $response->getHeaderLine('Retry-After'));
        $this->assertStringContainsString('Slow down.', (string) $response->getBody());
        $this->assertStringContainsString('429 Too Many Requests', (string) $response->getBody());
    }

    /**
     * @dataProvider reasons
     */
    public function testReasonPhrases(int $status, string $reason): void
    {
        $body = (string) (new ErrorHandler())->handle(new HttpException($status), $this->request())->getBody();

        $this->assertStringContainsString($status . ' ' . $reason, $body);
    }

    /**
     * @return array<string, array{int, string}>
     */
    public function reasons(): array
    {
        return [
            400 => [400, 'Bad Request'], 401 => [401, 'Unauthorized'], 403 => [403, 'Forbidden'], 404 => [404, 'Not Found'],
            405 => [405, 'Method Not Allowed'], 409 => [409, 'Conflict'], 419 => [419, 'Page Expired'], 422 => [422, 'Unprocessable Entity'],
            429 => [429, 'Too Many Requests'], 500 => [500, 'Internal Server Error'], 503 => [503, 'Service Unavailable'],
            'other 4xx' => [418, 'Error'], 'other 5xx' => [502, 'Server Error'],
        ];
    }

    public function testAnOrdinaryExceptionIsA500WithoutDetailsUnlessDebug(): void
    {
        $exception = new RuntimeException('secret detail');

        $production = (new ErrorHandler(false))->handle($exception, $this->request());
        $debug = (new ErrorHandler(true))->handle($exception, $this->request());

        $this->assertSame(500, $production->getStatusCode());
        $this->assertStringNotContainsString('secret detail', (string) $production->getBody());
        $this->assertStringNotContainsString('RuntimeException', (string) $production->getBody());
        $this->assertStringContainsString('secret detail', (string) $debug->getBody());
        $this->assertStringContainsString('RuntimeException', (string) $debug->getBody());
    }

    public function testJsonForApiClients(): void
    {
        $handler = new ErrorHandler(true);
        $exception = new RuntimeException('bad');

        foreach ([$this->request('application/json'), $this->request('text/html, application/json;q=0.9'), $this->request()->withHeader('X-Requested-With', 'XMLHttpRequest')] as $request) {
            $response = $handler->handle($exception, $request);

            $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
            $payload = json_decode((string) $response->getBody(), true);
            $this->assertSame(['status', 'message', 'exception', 'file', 'trace'], array_keys($payload));
            $this->assertSame(RuntimeException::class, $payload['exception']);
        }

        $quiet = json_decode((string) (new ErrorHandler(false))->handle($exception, $this->request('application/json'))->getBody(), true);
        $this->assertSame(['status' => 500, 'message' => 'Internal Server Error'], $quiet);
    }

    public function testTheBuiltInPageEscapesEverything(): void
    {
        $response = (new ErrorHandler(true))->handle(new HttpException(400, '<script>alert(1)</script>'), $this->request());

        $this->assertStringNotContainsString('<script>', (string) $response->getBody());
        $this->assertStringContainsString('&lt;script&gt;', (string) $response->getBody());
    }

    public function testErrorTemplatesAreUsedWhenTheyExist(): void
    {
        $templates = new class() implements TemplateEngineInterface {
            /**
             * @var list<array{string, array<string, mixed>}>
             */
            public array $rendered = [];

            public function render(string $name, array $params = []): string
            {
                $this->rendered[] = [$name, $params];

                return 'TEMPLATE ' . $name;
            }

            public function exists(string $name): bool
            {
                return $name === 'errors/404' || $name === 'errors/error';
            }
        };
        $handler = new ErrorHandler(false, $templates);

        $this->assertSame('TEMPLATE errors/404', (string) $handler->handle(new HttpException(404), $this->request())->getBody());
        $this->assertSame('TEMPLATE errors/error', (string) $handler->handle(new HttpException(403), $this->request())->getBody());
        $this->assertSame(['status' => 404, 'message' => 'Not Found', 'exception' => null], $templates->rendered[0][1]);

        $debugging = new ErrorHandler(true, $templates);
        $debugging->handle(new RuntimeException('x'), $this->request());
        $this->assertInstanceOf(RuntimeException::class, $templates->rendered[2][1]['exception'], 'debug passes the exception to the template');
    }

    public function testAnErrorPageThatThrowsFallsBackToTheBuiltInPage(): void
    {
        $templates = new class() implements TemplateEngineInterface {
            public function render(string $name, array $params = []): string
            {
                throw new RuntimeException('template is broken');
            }

            public function exists(string $name): bool
            {
                return true;
            }
        };

        $response = (new ErrorHandler(false, $templates))->handle(new HttpException(404), $this->request());

        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringContainsString('404 Not Found', (string) $response->getBody());
    }

    public function testOnlyServerErrorsAreLogged(): void
    {
        $logger = new class() extends AbstractLogger {
            /**
             * @var list<array{string, string}>
             */
            public array $records = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = [(string) $level, (string) $message];
            }
        };
        $handler = new ErrorHandler(false, null, $logger);

        $handler->handle(new HttpException(404), $this->request());
        $handler->handle(new HttpException(403), $this->request());
        $this->assertSame([], $logger->records);

        $handler->handle(new RuntimeException('database is down'), $this->request());
        $this->assertSame([['error', 'database is down']], $logger->records);
    }
}
