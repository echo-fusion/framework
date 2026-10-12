<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Tests\Fixtures;

use EchoFusion\Contracts\DatabaseManager\ConnectionInterface;
use EchoFusion\Contracts\TemplateManager\TemplateManagerInterface;
use EchoFusion\Framework\Exceptions\HttpException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

final class HomeController
{
    public function __construct(private readonly PostRepository $posts)
    {
    }

    public function index(): string
    {
        return 'home';
    }

    /**
     * @return array<string, mixed>
     */
    public function show(int $id, ?string $format = null): array
    {
        return $this->posts->find($id) + ['format' => $format];
    }

    public function inject(ServerRequestInterface $request, PostRepository $other, int $id = 7): array
    {
        return [
            'method' => $request->getMethod(),
            'sameRepositoryType' => $other instanceof PostRepository,
            'idAttribute' => $request->getAttribute('id'),
            'params' => $request->getAttribute('route.params'),
            'id' => $id,
        ];
    }

    public function forbidden(): never
    {
        throw new HttpException(403, 'You cannot edit this post.');
    }

    public function boom(): never
    {
        throw new RuntimeException('secret database password');
    }

    public function render(TemplateManagerInterface $templates): string
    {
        return $templates->render('home', ['name' => 'Ann']);
    }

    public function database(ConnectionInterface $db): array
    {
        return ['one' => $db->fetchValue('SELECT 1')];
    }

    public function nothing(): ?string
    {
        return null;
    }

    public function custom(): ResponseInterface
    {
        return new \GuzzleHttp\Psr7\Response(201, ['X-Custom' => 'yes'], 'made');
    }

    public function missingDependency(int $unknown): string
    {
        return 'never reached';
    }

    private function hidden(): string
    {
        return 'hidden';
    }
}
