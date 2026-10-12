<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Console\Commands;

use EchoFusion\Framework\Console\Input;
use EchoFusion\Framework\Console\Output;
use function preg_replace;
use function sprintf;
use function str_ends_with;
use function str_replace;
use function strtolower;

final class MakeCommandCommand extends MakeCommand
{
    private const STUB = <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace App\Console\Commands{NAMESPACE};

        use EchoFusion\Framework\Console\Command;
        use EchoFusion\Framework\Console\Input;
        use EchoFusion\Framework\Console\Output;

        final class {CLASS} extends Command
        {
            public function name(): string
            {
                return '{COMMAND}';
            }

            public function description(): string
            {
                return 'Describe what this command does';
            }

            public function handle(Input $input, Output $output): int
            {
                $output->info('{COMMAND} ran.');

                return self::SUCCESS;
            }
        }

        PHP;

    public function name(): string
    {
        return 'make:command';
    }

    public function description(): string
    {
        return 'Create a console command in app/Console/Commands';
    }

    public function arguments(): array
    {
        return ['name' => ['description' => 'Name, e.g. SendReport (the suffix Command is added)', 'required' => true]];
    }

    public function handle(Input $input, Output $output): int
    {
        [$namespace, $class] = $this->split((string) $input->argument('name'));
        $class = str_ends_with($class, 'Command') ? $class : $class . 'Command';
        $command = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', str_replace('Command', '', $class)));

        $directory = str_replace('\\', '/', $namespace) . '/';
        $this->write('app/Console/Commands' . $directory . $class . '.php', self::STUB, ['{NAMESPACE}' => $namespace, '{CLASS}' => $class, '{COMMAND}' => $command], $input, $output);

        $output->comment('Register it in config/console.php:');
        $output->writeln(sprintf("  'commands' => [App\\Console\\Commands%s\\%s::class],", $namespace, $class));
        $output->writeln(sprintf('Then run: php bin/console %s', $command));

        return self::SUCCESS;
    }
}
