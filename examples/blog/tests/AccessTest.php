<?php

declare(strict_types=1);

namespace Tests;

use App\Http\Middleware\RequireAdmin;
use App\Support\Access;
use EchoFusion\AuthManager\Users\GenericUser;
use EchoFusion\Framework\Exceptions\HttpException;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class AccessTest extends PHPUnitTestCase
{
    public function testAuthorsAndAdminsMayManageAPost(): void
    {
        $access = new Access();
        $post = ['user_id' => 2];

        $this->assertTrue($access->canManage($this->user(2, 'user'), $post), 'the author');
        $this->assertTrue($access->canManage($this->user(1, 'admin'), $post), 'an admin');
        $this->assertFalse($access->canManage($this->user(3, 'user'), $post), 'somebody else');
        $this->assertFalse($access->canManage(null, $post), 'a visitor');
    }

    public function testTheAdminMiddlewareLetsOnlyAdminsThrough(): void
    {
        $middleware = new RequireAdmin(new Access());
        $handler = new class() implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): Response
            {
                return new Response(200);
            }
        };

        $admin = (new ServerRequest('GET', '/admin'))->withAttribute('auth.user', $this->user(1, 'admin'));
        $this->assertSame(200, $middleware->process($admin, $handler)->getStatusCode());

        foreach ([(new ServerRequest('GET', '/admin'))->withAttribute('auth.user', $this->user(2, 'user')), new ServerRequest('GET', '/admin')] as $request) {
            try {
                $middleware->process($request, $handler);
                $this->fail('a non-admin must be stopped');
            } catch (HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }
    }

    private function user(int $id, string $role): GenericUser
    {
        return new GenericUser($id, 'x', ['name' => 'N', 'role' => $role]);
    }
}
