<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Console\Commands;

use Closure;
use EchoFusion\Contracts\RouteManager\RouteInterface;
use EchoFusion\Contracts\RouteManager\RouterInterface;
use EchoFusion\Framework\Console\Command;
use EchoFusion\Framework\Console\Input;
use EchoFusion\Framework\Console\Output;
use Psr\Container\ContainerInterface;
use function implode;
use function is_array;
use function is_string;
use function str_contains;
use function strtoupper;
use function usort;

final class RouteListCommand extends Command
{
    public function __construct(private readonly ContainerInterface $container)
    {
    }

    public function name(): string
    {
        return 'route:list';
    }

    public function description(): string
    {
        return 'List the routes: method, path, name, action and middleware';
    }

    public function options(): array
    {
        return [
            'method' => ['description' => 'Only routes for this method (GET, POST ...)', 'value' => true],
            'path' => ['description' => 'Only routes whose path contains this text', 'value' => true],
            'name' => ['description' => 'Only routes whose name contains this text', 'value' => true],
        ];
    }

    public function handle(Input $input, Output $output): int
    {
        $routes = $this->container->get(RouterInterface::class)->getRoutes();
        $method = $input->option('method');
        $path = $input->option('path');
        $name = $input->option('name');

        $rows = [];
        foreach ($routes as $route) {
            if (
                (is_string($method) && strtoupper($method) !== strtoupper($route->getMethod()))
                || (is_string($path) && !str_contains($route->getPath(), $path))
                || (is_string($name) && !str_contains($route->getName(), $name))
            ) {
                continue;
            }

            $rows[] = [strtoupper($route->getMethod()), $route->getPath(), $route->getName(), self::action($route), implode(', ', $route->getMiddlewares())];
        }

        usort($rows, static fn (array $a, array $b): int => [$a[1], $a[0]] <=> [$b[1], $b[0]]);

        if ($rows === []) {
            $output->warning('No routes match.');

            return self::SUCCESS;
        }

        $output->table(['Method', 'Path', 'Name', 'Action', 'Middleware'], $rows);

        return self::SUCCESS;
    }

    private static function action(RouteInterface $route): string
    {
        $action = $route->getAction();

        if ($action instanceof Closure) {
            return 'Closure';
        }

        return is_array($action) && isset($action[0], $action[1]) && is_string($action[0]) && is_string($action[1]) ? $action[0] . '@' . $action[1] : 'Closure';
    }
}
