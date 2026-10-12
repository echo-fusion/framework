<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Console;

use EchoFusion\Framework\Exceptions\UsageException;
use function array_key_exists;
use function array_shift;
use function count;
use function ltrim;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * The arguments and options of one command call, parsed against what the command declares.
 *
 * - `--name=value` and `--name value` (when the option takes a value), `--flag` (a switch), `-n value` and `-f` (short forms)
 * - everything after `--` is an argument, even if it starts with a dash
 *
 * A command declares its input in `Command::arguments()` and `Command::options()`.
 */
final class Input
{
    /**
     * @param array<string, mixed> $arguments
     * @param array<string, mixed> $options
     */
    private function __construct(private readonly array $arguments, private readonly array $options)
    {
    }

    /**
     * @param list<string> $tokens what the user typed after the command name
     * @param array<string, array{description?: string, required?: bool, default?: mixed}> $arguments
     * @param array<string, array{description?: string, short?: string, value?: bool, default?: mixed}> $options
     *
     * @throws UsageException
     */
    public static function parse(array $tokens, array $arguments = [], array $options = []): self
    {
        $options += ['help' => ['short' => 'h', 'description' => 'Show help for this command'], 'ansi' => ['description' => 'Force colors'], 'no-ansi' => ['description' => 'Turn colors off']];

        $shorts = [];
        foreach ($options as $name => $definition) {
            if (isset($definition['short'])) {
                $shorts[$definition['short']] = $name;
            }
        }

        $givenOptions = [];
        $positional = [];
        $onlyArguments = false;

        while ($tokens !== []) {
            $token = array_shift($tokens);

            if ($onlyArguments || $token === '-' || !str_starts_with($token, '-')) {
                $positional[] = $token;

                continue;
            }

            if ($token === '--') {
                $onlyArguments = true;

                continue;
            }

            if (str_starts_with($token, '--')) {
                $body = substr($token, 2);
                $inline = null;
                if (str_contains($body, '=')) {
                    [$body, $inline] = explode('=', $body, 2);
                }
                $name = $body;
            } else {
                $short = substr($token, 1, 1);
                $name = $shorts[$short] ?? null;
                if ($name === null) {
                    throw new UsageException(sprintf('The option "-%s" does not exist.', $short));
                }
                $inline = strlen($token) > 2 ? ltrim(substr($token, 2), '=') : null;
            }

            if (!array_key_exists($name, $options)) {
                throw new UsageException(sprintf('The option "--%s" does not exist.', $name));
            }

            if (empty($options[$name]['value'])) {
                if ($inline !== null) {
                    throw new UsageException(sprintf('The option "--%s" does not take a value.', $name));
                }
                $givenOptions[$name] = true;

                continue;
            }

            if ($inline === null) {
                if ($tokens === [] || (str_starts_with($tokens[0], '-') && $tokens[0] !== '-')) {
                    throw new UsageException(sprintf('The option "--%s" needs a value.', $name));
                }
                $inline = array_shift($tokens);
            }
            $givenOptions[$name] = $inline;
        }

        $values = [];
        foreach ($options as $name => $definition) {
            $values[$name] = $givenOptions[$name] ?? ($definition['default'] ?? (empty($definition['value']) ? false : null));
        }

        if (count($positional) > count($arguments)) {
            throw new UsageException(sprintf('Too many arguments: "%s" was not expected.', $positional[count($arguments)]));
        }

        $argumentValues = [];
        $index = 0;
        foreach ($arguments as $name => $definition) {
            if (array_key_exists($index, $positional)) {
                $argumentValues[$name] = $positional[$index];
            } elseif (!empty($definition['required']) && empty($values['help'])) {
                throw new UsageException(sprintf('Not enough arguments: "%s" is missing.', $name));
            } else {
                $argumentValues[$name] = $definition['default'] ?? null;
            }
            ++$index;
        }

        return new self($argumentValues, $values);
    }

    public function argument(string $name): mixed
    {
        return $this->arguments[$name] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function arguments(): array
    {
        return $this->arguments;
    }

    /**
     * A switch is `true` when given and `false` otherwise; an option that takes a value is that value, its default, or `null`.
     */
    public function option(string $name): mixed
    {
        return $this->options[$name] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function options(): array
    {
        return $this->options;
    }

    public function wantsHelp(): bool
    {
        return $this->options['help'] === true;
    }

    /**
     * `true` for --ansi, `false` for --no-ansi, `null` to decide from the terminal.
     */
    public function ansi(): ?bool
    {
        return $this->options['ansi'] === true ? true : ($this->options['no-ansi'] === true ? false : null);
    }
}
