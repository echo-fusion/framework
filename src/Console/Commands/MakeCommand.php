<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Console\Commands;

use EchoFusion\Framework\Application;
use EchoFusion\Framework\Console\Command;
use EchoFusion\Framework\Console\Input;
use EchoFusion\Framework\Console\Output;
use EchoFusion\Framework\Exceptions\UsageException;
use function array_keys;
use function array_pop;
use function array_values;
use function dirname;
use function explode;
use function file_exists;
use function file_put_contents;
use function implode;
use function is_dir;
use function mkdir;
use function preg_match;
use function sprintf;
use function str_replace;
use function trim;

/**
 * Shared by the `make:*` commands: write a file from a template, refusing to overwrite one unless `--force`.
 */
abstract class MakeCommand extends Command
{
    public function __construct(protected readonly Application $application)
    {
    }

    public function options(): array
    {
        return ['force' => ['description' => 'Overwrite the file if it exists']];
    }

    /**
     * @param array<string, string> $replacements placeholder => value
     *
     * @throws UsageException when the file exists and --force was not given
     */
    protected function write(string $relativePath, string $stub, array $replacements, Input $input, Output $output): void
    {
        $path = $this->application->path($relativePath);
        if (file_exists($path) && $input->option('force') !== true) {
            throw new UsageException(sprintf('%s already exists. Use --force to overwrite it.', $relativePath));
        }

        if (!is_dir(dirname($path)) && !@mkdir(dirname($path), 0775, true) && !is_dir(dirname($path))) {
            throw new UsageException(sprintf('The directory "%s" could not be created.', dirname($path)));
        }

        file_put_contents($path, str_replace(array_keys($replacements), array_values($replacements), $stub));
        $output->success(sprintf('Created %s', $relativePath));
    }

    /**
     * `Admin/Post` becomes the namespace part `Admin` and the class name `Post`.
     *
     * @return array{0: string, 1: string} namespace suffix (with a leading backslash, or empty) and class name
     *
     * @throws UsageException
     */
    protected function split(string $name): array
    {
        $name = str_replace('\\', '/', trim($name, '/\\'));
        if (preg_match('#^[A-Z][A-Za-z0-9]*(/[A-Z][A-Za-z0-9]*)*$#', $name) !== 1) {
            throw new UsageException(sprintf('"%s" is not a valid name: use StudlyCase words, optionally in folders like Admin/Post.', $name));
        }

        $parts = explode('/', $name);
        $class = array_pop($parts);

        return [$parts === [] ? '' : '\\' . implode('\\', $parts), $class];
    }
}
