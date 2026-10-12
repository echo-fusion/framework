<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Tests\Console;

use EchoFusion\Framework\Console\Command;
use EchoFusion\Framework\Console\Input;
use EchoFusion\Framework\Console\Output;
use function strtoupper;

final class HelloCommand extends Command
{
    public function name(): string
    {
        return 'hello';
    }

    public function description(): string
    {
        return 'Greets someone';
    }

    public function arguments(): array
    {
        return ['name' => ['description' => 'Who', 'required' => true]];
    }

    public function options(): array
    {
        return ['loud' => ['description' => 'Shout']];
    }

    public function handle(Input $input, Output $output): int
    {
        $text = 'Hello ' . $input->argument('name');
        $output->writeln($input->option('loud') === true ? strtoupper($text) : $text);

        return self::SUCCESS;
    }
}
