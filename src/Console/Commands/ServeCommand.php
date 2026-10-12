<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Console\Commands;

use EchoFusion\Framework\Application;
use EchoFusion\Framework\Console\Command;
use EchoFusion\Framework\Console\Input;
use EchoFusion\Framework\Console\Output;
use EchoFusion\Framework\Exceptions\UsageException;
use const PHP_BINARY;
use function escapeshellarg;
use function is_dir;
use function is_numeric;
use function passthru;
use function sprintf;

final class ServeCommand extends Command
{
    public function __construct(private readonly Application $application)
    {
    }

    public function name(): string
    {
        return 'serve';
    }

    public function description(): string
    {
        return 'Start the PHP development server for public/ (for development only)';
    }

    public function options(): array
    {
        return [
            'host' => ['description' => 'Address to listen on', 'value' => true, 'default' => '127.0.0.1'],
            'port' => ['description' => 'Port to listen on', 'short' => 'p', 'value' => true, 'default' => '8000'],
            'dry-run' => ['description' => 'Only print the command'],
        ];
    }

    public function handle(Input $input, Output $output): int
    {
        $host = (string) $input->option('host');
        $port = (string) $input->option('port');
        if (!is_numeric($port) || (int) $port < 1 || (int) $port > 65535) {
            throw new UsageException(sprintf('"%s" is not a valid port.', $port));
        }

        $public = $this->application->path('public');
        if (!is_dir($public)) {
            throw new UsageException(sprintf('The directory "%s" does not exist.', $public));
        }

        $command = sprintf('%s -S %s -t %s', escapeshellarg(PHP_BINARY), escapeshellarg($host . ':' . $port), escapeshellarg($public));

        if ($input->option('dry-run') === true) {
            $output->writeln($command);

            return self::SUCCESS;
        }

        $output->info(sprintf('EchoFusion development server: http://%s:%s  (Ctrl+C to stop)', $host, $port));
        passthru($command, $status);

        return $status;
    }
}
