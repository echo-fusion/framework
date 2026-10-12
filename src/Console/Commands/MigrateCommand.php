<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Console\Commands;

use EchoFusion\Framework\Console\Input;
use EchoFusion\Framework\Console\Output;
use function is_string;
use function sprintf;

final class MigrateCommand extends MigrationCommand
{
    public function name(): string
    {
        return 'migrate';
    }

    public function description(): string
    {
        return 'Run the migrations that have not run yet';
    }

    public function handle(Input $input, Output $output): int
    {
        $database = $input->option('database');
        $count = $this->runner(is_string($database) ? $database : null)->migrate(
            static fn (string $name) => $output->writeln(sprintf('%s %s', $output->color('info', 'Migrated:'), $name)),
        );

        $count === 0 ? $output->comment('Nothing to migrate.') : $output->success(sprintf('%d migration%s ran.', $count, $count === 1 ? '' : 's'));

        return self::SUCCESS;
    }
}
