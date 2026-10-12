<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Console\Commands;

use EchoFusion\Framework\Console\Input;
use EchoFusion\Framework\Console\Output;
use function sprintf;
use function str_ends_with;
use function str_replace;
use function strtolower;

final class MakeControllerCommand extends MakeCommand
{
    private const STUB = <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace App\Http\Controllers{NAMESPACE};

        use EchoFusion\Framework\Http\Request;

        final class {CLASS}
        {
            public function index(Request $request): array
            {
                return ['controller' => '{CLASS}'];
            }
        }

        PHP;

    public function name(): string
    {
        return 'make:controller';
    }

    public function description(): string
    {
        return 'Create a controller in app/Http/Controllers';
    }

    public function arguments(): array
    {
        return ['name' => ['description' => 'Name, e.g. Post or Admin/Post (the suffix Controller is added)', 'required' => true]];
    }

    public function handle(Input $input, Output $output): int
    {
        [$namespace, $class] = $this->split((string) $input->argument('name'));
        $class = str_ends_with($class, 'Controller') ? $class : $class . 'Controller';

        $directory = str_replace('\\', '/', $namespace) . '/';
        $this->write('app/Http/Controllers' . $directory . $class . '.php', self::STUB, ['{NAMESPACE}' => $namespace, '{CLASS}' => $class], $input, $output);

        $path = '/' . strtolower(str_replace('Controller', '', $class));
        $output->comment('Add a route in config/routes.php:');
        $output->writeln(sprintf("  '%s.index' => ['method' => 'GET', 'path' => '%s', 'action' => [App\\Http\\Controllers%s\\%s::class, 'index']],", strtolower(str_replace('Controller', '', $class)), $path, $namespace, $class));

        return self::SUCCESS;
    }
}
