<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Console;

/**
 * A console command. Extend it, list the class in `config/console.php` and run it with `php bin/console <name>`.
 *
 * The container builds the command, so ask for the services you need in the constructor (keep it cheap: every
 * command is built when `list` is shown).
 *
 * <code>
 * final class SendReportCommand extends Command
 * {
 *     public function __construct(private readonly Mailer $mailer) {}
 *
 *     public function name(): string { return 'report:send'; }
 *     public function description(): string { return 'Email the weekly report'; }
 *     public function arguments(): array { return ['to' => ['description' => 'Who gets it', 'required' => true]]; }
 *     public function options(): array { return ['dry-run' => ['description' => 'Do not send']]; }
 *
 *     public function handle(Input $input, Output $output): int
 *     {
 *         $output->info('Sending to ' . $input->argument('to'));
 *
 *         return self::SUCCESS;
 *     }
 * }
 * </code>
 */
abstract class Command
{
    public const SUCCESS = 0;

    public const FAILURE = 1;

    public const INVALID = 2;

    /**
     * The name typed after `console`; use `group:action` so related commands list together.
     */
    abstract public function name(): string;

    abstract public function description(): string;

    /**
     * Positional arguments, in order.
     *
     * @return array<string, array{description?: string, required?: bool, default?: mixed}>
     */
    public function arguments(): array
    {
        return [];
    }

    /**
     * Options. A switch (`--force`) is `true` when given; with `'value' => true` it takes a value (`--port=8000`).
     * `--help`, `--ansi` and `--no-ansi` are always available.
     *
     * @return array<string, array{description?: string, short?: string, value?: bool, default?: mixed}>
     */
    public function options(): array
    {
        return [];
    }

    /**
     * Do the work and return an exit code: {@see SUCCESS} (0), {@see FAILURE} (1) or {@see INVALID} (2).
     */
    abstract public function handle(Input $input, Output $output): int;
}
