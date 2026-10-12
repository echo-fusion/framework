<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Console\Commands;

use EchoFusion\Framework\Console\Input;
use EchoFusion\Framework\Console\Output;
use function array_map;
use function is_string;

final class MigrateStatusCommand extends MigrationCommand
{
    public function name(): string
    {
        return 'migrate:status';
    }

    public function description(): string
    {
        return 'Show which migrations have run';
    }

    public function handle(Input $input, Output $output): int
    {
        $database = $input->option('database');
        $status = $this->runner(is_string($database) ? $database : null)->status();

        if ($status === []) {
            $output->comment('There are no migration files in database/migrations.');

            return self::SUCCESS;
        }

        $output->table(['Migration', 'Status', 'Batch'], array_map(
            static fn (array $row): array => [$row['migration'], $row['batch'] === null ? 'pending' : 'ran', $row['batch'] === null ? '' : (string) $row['batch']],
            $status,
        ));

        return self::SUCCESS;
    }
}
