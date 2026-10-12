<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Tests\Fixtures;

use EchoFusion\Contracts\PluginManager\PluginInterface;
use EchoFusion\Contracts\ServiceManager\SettableContainerInterface;

final class ProdOnlyPlugin implements PluginInterface
{
    public function register(SettableContainerInterface $container): void
    {
        $container->set('plugin.prod', static fn (): string => 'prod plugin active', true);
    }

    public function getSupportedEnvironments(): array
    {
        return ['production'];
    }
}
