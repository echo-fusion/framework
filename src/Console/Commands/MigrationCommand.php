<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Console\Commands;

use EchoFusion\Contracts\DatabaseManager\ConnectionInterface;
use EchoFusion\Contracts\DatabaseManager\DatabaseManagerInterface;
use EchoFusion\Framework\Application;
use EchoFusion\Framework\Console\Command;
use EchoFusion\Framework\Database\MigrationRunner;
use Psr\Container\ContainerInterface;
use function is_string;

/**
 * Shared by the migration commands: builds the runner for the chosen connection.
 */
abstract class MigrationCommand extends Command
{
    public function __construct(private readonly Application $application, private readonly ContainerInterface $container)
    {
    }

    public function options(): array
    {
        return ['database' => ['description' => 'Name of the connection from config/database.php (default: the default one)', 'value' => true]];
    }

    protected function runner(?string $connection): MigrationRunner
    {
        $db = is_string($connection) && $connection !== ''
            ? $this->container->get(DatabaseManagerInterface::class)->connection($connection)
            : $this->container->get(ConnectionInterface::class);

        return new MigrationRunner($db, $this->application->path('database/migrations'));
    }
}
