<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Console\Commands;

use EchoFusion\Framework\Application;
use EchoFusion\Framework\Console\Command;
use EchoFusion\Framework\Console\Input;
use EchoFusion\Framework\Console\Output;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use function is_dir;
use function rmdir;
use function sprintf;
use function unlink;

final class CacheClearCommand extends Command
{
    public function __construct(private readonly Application $application)
    {
    }

    public function name(): string
    {
        return 'cache:clear';
    }

    public function description(): string
    {
        return 'Delete the compiled templates and other files in storage/cache';
    }

    public function handle(Input $input, Output $output): int
    {
        $directory = $this->application->path('storage/cache');
        if (!is_dir($directory)) {
            $output->warning(sprintf('Nothing to clear: "%s" does not exist.', $directory));

            return self::SUCCESS;
        }

        $removed = 0;
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            if ($item->getFilename() === '.gitignore') {
                continue;
            }

            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
            ++$removed;
        }

        $output->success(sprintf('Cache cleared (%d items removed).', $removed));

        return self::SUCCESS;
    }
}
