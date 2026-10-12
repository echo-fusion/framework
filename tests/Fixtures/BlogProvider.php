<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Tests\Fixtures;

use EchoFusion\Contracts\ServiceManager\SettableContainerInterface;
use EchoFusion\Contracts\ServiceProvider\ServiceProviderInterface;

final class BlogProvider implements ServiceProviderInterface
{
    public function register(SettableContainerInterface $container): void
    {
        EventLog::$entries[] = 'blog:register';
        $container->set('blog.service', static fn (): string => 'blog service', true);
    }

    public function boot(SettableContainerInterface $container, array $config = []): void
    {
        EventLog::$entries[] = 'blog:boot:' . ($config === [] ? 'empty' : 'configured');
    }
}
