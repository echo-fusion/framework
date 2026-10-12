<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Tests;

use EchoFusion\Framework\Tests\Fixtures\HomeController;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

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
