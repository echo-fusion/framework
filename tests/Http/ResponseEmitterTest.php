<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Tests\Http;

use EchoFusion\Framework\Exceptions\FrameworkException;
use EchoFusion\Framework\Http\ResponseEmitter;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use function str_repeat;

/**
 * PHP only sends headers before any output, and PHPUnit prints output, so each test runs in its own process.
 *
 * @runTestsInSeparateProcesses
 *
 * @preserveGlobalState disabled
 */
final class ResponseEmitterTest extends TestCase
{
    public function testTheBodyIsWrittenInFull(): void
    {
        $this->expectOutputString('hello world');

        (new ResponseEmitter())->emit(new Response(200, ['Content-Type' => 'text/plain'], 'hello world'));
    }

    public function testALargeBodyIsStreamedInChunks(): void
    {
        $body = str_repeat('x', 100000);
        $this->expectOutputString($body);

        (new ResponseEmitter())->emit(new Response(200, [], $body));
    }

    public function testASeekableBodyThatWasAlreadyReadIsSentFromTheStart(): void
    {
        $response = new Response(200, [], 'again');
        (string) $response->getBody(); // reading moves the pointer to the end
        $this->expectOutputString('again');

        (new ResponseEmitter())->emit($response);
    }

    public function testHeadRequestsGetNoBody(): void
    {
        $this->expectOutputString('');

        (new ResponseEmitter())->emit(new Response(200, [], 'not sent'), new ServerRequest('HEAD', '/'));
    }

    /**
     * @dataProvider statusesWithoutABody
     */
    public function testNoBodyForStatusesThatForbidIt(int $status): void
    {
        $this->expectOutputString('');

        (new ResponseEmitter())->emit(new Response($status, [], 'not sent'));
    }

    /**
     * @return array<string, array{int}>
     */
    public function statusesWithoutABody(): array
    {
        return ['204' => [204], '304' => [304], '100' => [100]];
    }

    public function testSeveralHeadersOfTheSameNameAreAllSent(): void
    {
        $this->expectOutputString('ok');

        (new ResponseEmitter())->emit(new Response(200, ['Set-Cookie' => ['a=1', 'b=2'], 'X-Test' => ['one', 'two']], 'ok'));
    }

    public function testItRefusesToSendOnceOutputHasStarted(): void
    {
        // PHPUnit buffers output; flush it so PHP considers the headers sent, then restore the buffers
        $level = ob_get_level();
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        echo 'already sent';
        flush();
        for ($i = 0; $i < $level; ++$i) {
            ob_start();
        }

        $this->expectException(FrameworkException::class);
        $this->expectExceptionMessage('output already started');

        (new ResponseEmitter())->emit(new Response(200));
    }
}
