<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Console\Commands;

use EchoFusion\Framework\Console\Input;
use EchoFusion\Framework\Console\Output;
use function is_string;
use function sprintf;

final class MigrateRollbackCommand extends MigrationCommand
{
    public function name(): string
    {
        return 'migrate:rollback';
    }

    public function description(): string
    {
        return 'Undo the last batch of migrations';
    }

    public function handle(Input $input, Output $output): int
    {
        $database = $input->option('database');
        $count = $this->runner(is_string($database) ? $database : null)->rollback(
            static fn (string $name) => $output->writeln(sprintf('%s %s', $output->color('warning', 'Rolled back:'), $name)),
        );

        $count === 0 ? $output->comment('Nothing to roll back.') : $output->success(sprintf('%d migration%s rolled back.', $count, $count === 1 ? '' : 's'));

        return self::SUCCESS;
    }
}
