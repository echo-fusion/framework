<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Tests;

use EchoFusion\Contracts\SessionManager\SessionInterface;
use EchoFusion\Framework\Application;
use EchoFusion\Framework\Http\Middleware\VerifyCsrfToken;
use EchoFusion\Framework\Tests\Fixtures\FormController;
use EchoFusion\SessionManager\ArraySession;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use const JSON_THROW_ON_ERROR;

/**
 * A form page and its submission, through the whole application.
 */
final class RequestIntegrationTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        $this->app = TestApp::create([
            'app' => ['debug' => true, 'csrf' => ['except' => ['/api/*']]],
            'middleware' => ['core' => [VerifyCsrfToken::class]],
            'routes' => [
                'form' => ['method' => 'GET', 'path' => '/form', 'action' => [FormController::class, 'show']],
                'submit' => ['method' => 'POST', 'path' => '/form', 'action' => [FormController::class, 'submit']],
                'update' => ['method' => 'PUT', 'path' => '/form', 'action' => [FormController::class, 'submit']],
                'api' => ['method' => 'POST', 'path' => '/api/form', 'action' => [FormController::class, 'submit']],
                'wrapped' => ['method' => 'GET', 'path' => '/wrapped', 'action' => [FormController::class, 'rawAndWrapped']],
            ],
        ]);

        $session = new ArraySession();
        $this->app->getContainer()->set(SessionInterface::class, static fn () => $session, true);
    }

    protected function tearDown(): void
    {
        TestApp::cleanUp();
    }

    /**
     * @return array<string, mixed>
     */
    private function json(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function token(): string
    {
        return $this->json($this->app->handle(new ServerRequest('GET', '/form')))['token'];
    }

    public function testAFormIsSubmittedWithItsToken(): void
    {
        $token = $this->token();

        $request = (new ServerRequest('POST', '/form?page=3', ['Content-Type' => 'application/x-www-form-urlencoded']))
            ->withQueryParams(['page' => '3'])
            ->withParsedBody(['_token' => $token, 'title' => 'Hello', 'tags' => ['a', 'b'], 'ignored' => 'x']);
        $data = $this->json($this->app->handle($request));

        $this->assertSame('POST', $data['method']);
        $this->assertSame('Hello', $data['title']);
        $this->assertSame('3', $data['page']);
        $this->assertSame(['title' => 'Hello', 'tags' => ['a', 'b']], $data['only']);
    }

    public function testASubmissionWithoutTheTokenExpires(): void
    {
        $this->token();

        $response = $this->app->handle((new ServerRequest('POST', '/form'))->withParsedBody(['title' => 'Hello']));

        $this->assertSame(419, $response->getStatusCode());
        $this->assertStringContainsString('Page Expired', (string) $response->getBody());
    }

    public function testAJsonApiCallIsExceptedFromTheToken(): void
    {
        $response = $this->app->handle(new ServerRequest('POST', '/api/form', ['Content-Type' => 'application/json', 'Accept' => 'application/json'], '{"title":"From JSON"}'));

        $data = $this->json($response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('From JSON', $data['title']);
        $this->assertTrue($data['wantsJson']);
    }

    public function testAnUpdateWithTheTokenInAHeader(): void
    {
        $token = $this->token();

        $response = $this->app->handle(new ServerRequest('PUT', '/form', ['X-CSRF-TOKEN' => $token, 'Content-Type' => 'application/x-www-form-urlencoded'], 'title=Edited'));

        $this->assertSame('Edited', $this->json($response)['title']);
    }

    public function testRequestAndCustomRequestAndTheRawRequestCanAllBeInjected(): void
    {
        $response = $this->app->handle((new ServerRequest('GET', '/wrapped'))->withQueryParams(['title' => 'custom']));

        $this->assertSame(['same' => true, 'custom' => 'CUSTOM'], $this->json($response));
    }
}
