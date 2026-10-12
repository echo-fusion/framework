<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Tests\Fixtures;

use EchoFusion\ModuleManager\AbstractModule;

final class BlogModule extends AbstractModule
{
    public function getName(): string
    {
        return 'blog';
    }

    public function getConfig(): array
    {
        return ['blog' => ['posts' => 3, 'title' => 'From the module'], 'app' => ['name' => 'module-name']];
    }

    public function getProviders(): array
    {
        return [BlogProvider::class];
    }

    public function getRoutes(): array
    {
        return ['blog.index' => ['method' => 'GET', 'path' => '/blog', 'action' => [BlogController::class, 'index']]];
    }

    public function getTemplatePath(): ?string
    {
        return __DIR__ . '/templates/blog';
    }
}
