<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Tests;

use EchoFusion\Framework\Http\GlobalsRequestFactory;
use EchoFusion\Framework\Tests\Fixtures\HomeController;
use GuzzleHttp\Psr7\ServerRequest;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

/**
 * `run()` sends the response, so it needs a process without earlier output.
 *
 * @runTestsInSeparateProcesses
 *
 * @preserveGlobalState disabled
 */
final class RunTest extends TestCase
{
    protected function tearDown(): void
    {
        TestApp::cleanUp();
    }

    public function testRunHandlesTheRequestAndSendsTheResponse(): void
    {
        $app = TestApp::create(['routes' => ['home' => ['method' => 'GET', 'path' => '/', 'action' => [HomeController::class, 'index']]]]);
        $this->expectOutputString('home');

        $app->run(new ServerRequest('GET', '/'));
    }

    public function testRunSendsErrorPagesToo(): void
    {
        $app = TestApp::create(['app' => ['debug' => false]]);
        $this->expectOutputRegex('/404 Not Found/');

        $app->run(new ServerRequest('GET', '/nothing-here'));
    }

    public function testAMalformedRequestIsAnsweredWith400NotAPhpError(): void
    {
        $app = TestApp::create(['app' => ['debug' => false]]);
        $app->getContainer()->set(GlobalsRequestFactory::class, static fn () => new class() extends GlobalsRequestFactory {
            public function create(): ServerRequestInterface
            {
                throw new InvalidArgumentException('" Accept" is not valid header name.');
            }
        }, true);
        $this->expectOutputRegex('/400 Bad Request.*The request is malformed\./s');

        $app->run();
    }

    public function testRunReadsTheRequestFromThePhpGlobalsByDefault(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['SERVER_NAME'] = 'localhost';
        $app = TestApp::create(['routes' => ['home' => ['method' => 'GET', 'path' => '/', 'action' => [HomeController::class, 'index']]]]);
        $this->expectOutputString('home');

        $app->run();
    }
}
