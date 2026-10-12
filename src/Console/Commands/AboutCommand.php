<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Console\Commands;

use Composer\InstalledVersions;
use EchoFusion\Contracts\EventManager\EventManagerInterface;
use EchoFusion\Contracts\ModuleManager\ModuleManagerInterface;
use EchoFusion\Contracts\RouteManager\RouterInterface;
use EchoFusion\Framework\Application;
use EchoFusion\Framework\Console\Command;
use EchoFusion\Framework\Console\Input;
use EchoFusion\Framework\Console\Output;
use Psr\Container\ContainerInterface;
use const PHP_OS_FAMILY;
use const PHP_VERSION;
use function count;
use function date_default_timezone_get;
use function sprintf;

final class AboutCommand extends Command
{
    public function __construct(private readonly Application $application, private readonly ContainerInterface $container)
    {
    }

    public function name(): string
    {
        return 'about';
    }

    public function description(): string
    {
        return 'Show the environment, versions and counts of this application';
    }

    public function handle(Input $input, Output $output): int
    {
        $container = $this->container;
        $router = $container->get(RouterInterface::class);

        $rows = [
            ['Application', (string) $this->application->getConfig()->get('app.name', 'EchoFusion')],
            ['Environment', $this->application->getEnvironment()],
            ['Debug', $this->application->isDebug() ? 'on' : 'off'],
            ['Timezone', date_default_timezone_get()],
            ['PHP', PHP_VERSION . ' (' . PHP_OS_FAMILY . ')'],
            ['Framework', self::frameworkVersion()],
            ['Base path', $this->application->path()],
            ['Routes', (string) count($router->getRoutes())],
            ['Modules', (string) count($container->get(ModuleManagerInterface::class)->all())],
            ['Listeners', $this->listeners($container)],
        ];

        $output->title('Application');
        $width = 14;
        foreach ($rows as [$label, $value]) {
            $output->writeln(sprintf('  %s  %s', $output->color('info', str_pad($label, $width)), $value));
        }

        return self::SUCCESS;
    }

    public static function frameworkVersion(): string
    {
        return class_exists(InstalledVersions::class) && InstalledVersions::isInstalled('echo-fusion/framework')
            ? (string) InstalledVersions::getPrettyVersion('echo-fusion/framework')
            : 'dev';
    }

    private function listeners(ContainerInterface $container): string
    {
        $events = $container->get(EventManagerInterface::class);

        return method_exists($events, 'hasListeners') ? 'attached' : 'n/a';
    }
}
