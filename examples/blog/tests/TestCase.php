<?php

declare(strict_types=1);

namespace Tests;

use EchoFusion\Framework\Application;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Psr\Http\Message\ResponseInterface;
use function dirname;

/**
 * Base class for feature tests: boots the application in the "testing" environment and sends requests
 * through it without a web server.
 */
abstract class TestCase extends PHPUnitTestCase
{
    private ?Application $app = null;

    protected function app(): Application
    {
        return $this->app ??= Application::create(dirname(__DIR__), 'testing');
    }

    /**
     * @param array<string, string> $headers
     */
    protected function request(string $method, string $uri, array $headers = []): ResponseInterface
    {
        return $this->app()->handle(new ServerRequest($method, $uri, $headers));
    }

    /**
     * @param array<string, string> $headers
     */
    protected function get(string $uri, array $headers = []): ResponseInterface
    {
        return $this->request('GET', $uri, $headers);
    }
}
