<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Console;

use EchoFusion\Framework\Exceptions\FrameworkException;
use EchoFusion\Framework\Exceptions\UsageException;
use Psr\Container\ContainerInterface;
use Throwable;
use function array_filter;
use function array_keys;
use function array_map;
use function array_shift;
use function array_slice;
use function array_values;
use function count;
use function explode;
use function in_array;
use function ksort;
use function levenshtein;
use function max;
use function mb_strlen;
use function sprintf;
use function str_contains;
use function str_pad;
use function str_starts_with;
use function usort;

/**
 * The command line kernel: finds the command for what was typed, parses its input and runs it.
 *
 * `php bin/console` lists the commands, `php bin/console route:list` runs one, `php bin/console route:list --help` explains it.
 * The exit code is the command's: 0 success, 1 failure, 2 wrong usage (unknown command, unknown option, missing argument).
 */
final class Console
{
    /**
     * @var array<string, Command>
     */
    private array $commands = [];

    /**
     * @param list<class-string<Command>|Command> $commands
     */
    public function __construct(
        private readonly ContainerInterface $container,
        array $commands,
        private readonly Output $output = new Output(),
        private readonly bool $debug = false,
        private readonly string $version = 'dev',
    ) {
        foreach ($commands as $command) {
            $this->add($command instanceof Command ? $command : $this->build($command));
        }
    }

    /**
     * @throws FrameworkException when two commands have the same name
     */
    public function add(Command $command): void
    {
        $name = $command->name();
        if (isset($this->commands[$name])) {
            throw new FrameworkException(sprintf('The console command "%s" is registered twice (%s and %s).', $name, $this->commands[$name]::class, $command::class));
        }

        $this->commands[$name] = $command;
        ksort($this->commands);
    }

    /**
     * @return array<string, Command>
     */
    public function commands(): array
    {
        return $this->commands;
    }

    /**
     * @param list<string> $argv as PHP gives it: the script name first
     */
    public function run(array $argv): int
    {
        array_shift($argv);

        $name = null;
        if ($argv !== [] && !str_starts_with($argv[0], '-')) {
            $name = array_shift($argv);
        } elseif ($argv !== [] && in_array($argv[0], ['-h', '--help'], true)) {
            $name = 'list';
        }

        $name ??= 'list';
        if ($name === 'help') {
            $name = $argv === [] ? 'list' : array_shift($argv);
            $argv[] = '--help';
        }

        if ($name === 'list') {
            $this->describeAll($this->output->withAnsi(in_array('--no-ansi', $argv, true) ? false : (in_array('--ansi', $argv, true) ? true : null)));

            return Command::SUCCESS;
        }

        $command = $this->commands[$name] ?? null;
        if ($command === null) {
            $this->output->error(sprintf('Command "%s" is not defined.', $name));
            $suggestions = $this->suggest($name);
            if ($suggestions !== []) {
                $this->output->writeln('Did you mean ' . (count($suggestions) === 1 ? 'this' : 'one of these') . '?');
                foreach ($suggestions as $suggestion) {
                    $this->output->writeln('    ' . $suggestion);
                }
            }

            return Command::INVALID;
        }

        try {
            $input = Input::parse($argv, $command->arguments(), $command->options());
        } catch (UsageException $e) {
            $this->output->error($e->getMessage());
            $this->output->writeln();
            $this->describe($command, $this->output);

            return Command::INVALID;
        }

        $output = $this->output->withAnsi($input->ansi());

        if ($input->wantsHelp()) {
            $this->describe($command, $output);

            return Command::SUCCESS;
        }

        try {
            return $command->handle($input, $output);
        } catch (UsageException $e) {
            $output->error($e->getMessage());

            return Command::INVALID;
        } catch (Throwable $e) {
            $output->error($e->getMessage());
            if ($this->debug) {
                $output->comment(sprintf('%s in %s:%d', $e::class, $e->getFile(), $e->getLine()));
                $output->comment($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    /**
     * The command list, as shown by `list`.
     */
    public function describeAll(Output $output): void
    {
        $output->title('EchoFusion ' . $this->version);
        $output->newLine();
        $output->writeln('Usage:');
        $output->writeln('  php bin/console <command> [options] [arguments]');
        $output->newLine();

        $width = max(array_map(static fn (string $name): int => mb_strlen($name), [...array_map('strval', array_keys($this->commands)), 'help', 'list']));
        $output->title('Commands');
        $output->writeln('  ' . $output->color('info', str_pad('list', $width)) . '  Show this list');
        $output->writeln('  ' . $output->color('info', str_pad('help', $width)) . '  Show the help of a command: help <command>');
        $group = 'Commands';
        foreach ($this->commands as $name => $command) {
            $prefix = str_contains($name, ':') ? explode(':', $name)[0] : null;
            if ($prefix !== $group) {
                $output->title($prefix ?? 'Commands');
                $group = $prefix;
            }
            $output->writeln('  ' . $output->color('info', str_pad($name, $width)) . '  ' . $command->description());
        }

        $output->newLine();
        $output->comment('Run "php bin/console <command> --help" for the arguments and options of a command.');
    }

    private function describe(Command $command, Output $output): void
    {
        $output->title($command->name());
        $output->writeln($command->description());
        $output->newLine();

        $arguments = $command->arguments();
        $options = $command->options() + ['help' => ['short' => 'h', 'description' => 'Show this help'], 'no-ansi' => ['description' => 'Turn colors off']];

        $usage = 'php bin/console ' . $command->name();
        foreach ($arguments as $argument => $definition) {
            $usage .= empty($definition['required']) ? sprintf(' [<%s>]', $argument) : sprintf(' <%s>', $argument);
        }
        $output->writeln('Usage:');
        $output->writeln('  ' . $usage . ' [options]');

        if ($arguments !== []) {
            $output->newLine();
            $output->writeln('Arguments:');
            foreach ($arguments as $argument => $definition) {
                $output->writeln(sprintf('  %s  %s%s', $output->color('info', str_pad($argument, 14)), $definition['description'] ?? '', isset($definition['default']) ? sprintf(' [default: %s]', (string) $definition['default']) : ''));
            }
        }

        $output->newLine();
        $output->writeln('Options:');
        foreach ($options as $option => $definition) {
            $label = (isset($definition['short']) ? '-' . $definition['short'] . ', ' : '    ') . '--' . $option . (empty($definition['value']) ? '' : '=VALUE');
            $output->writeln(sprintf('  %s  %s%s', $output->color('info', str_pad($label, 22)), $definition['description'] ?? '', isset($definition['default']) && !empty($definition['value']) ? sprintf(' [default: %s]', (string) $definition['default']) : ''));
        }
    }

    /**
     * @return list<string>
     */
    private function suggest(string $name): array
    {
        $names = array_keys($this->commands);
        $close = array_filter($names, static fn (string $candidate): bool => levenshtein($name, $candidate) <= 3 || str_starts_with($candidate, $name) || str_contains($candidate, $name));
        usort($close, static fn (string $a, string $b): int => levenshtein($name, $a) <=> levenshtein($name, $b));

        return array_values(array_slice($close, 0, 5));
    }

    /**
     * @param class-string<Command> $class
     */
    private function build(string $class): Command
    {
        $command = $this->container->get($class);
        if (!$command instanceof Command) {
            throw new FrameworkException(sprintf('"%s" is listed as a console command but is not a %s.', $class, Command::class));
        }

        return $command;
    }
}
