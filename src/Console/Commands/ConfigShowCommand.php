<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Console\Commands;

use Closure;
use EchoFusion\Contracts\ConfigManager\ConfigInterface;
use EchoFusion\Framework\Console\Command;
use EchoFusion\Framework\Console\Input;
use EchoFusion\Framework\Console\Output;
use EchoFusion\Framework\Exceptions\UsageException;
use Psr\Container\ContainerInterface;
use const JSON_PARTIAL_OUTPUT_ON_ERROR;
use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use function is_array;
use function is_object;
use function is_string;
use function json_encode;
use function preg_match;
use function sprintf;

final class ConfigShowCommand extends Command
{
    private const SECRET_KEYS = '/pass|secret|token|key|credential|dsn/i';

    public function __construct(private readonly ContainerInterface $container)
    {
    }

    public function name(): string
    {
        return 'config:show';
    }

    public function description(): string
    {
        return 'Show the merged configuration, or one value (secrets are hidden)';
    }

    public function arguments(): array
    {
        return ['key' => ['description' => 'A key with dots, e.g. database.connections.main. Omit to show everything']];
    }

    public function handle(Input $input, Output $output): int
    {
        $config = $this->container->get(ConfigInterface::class);
        $key = $input->argument('key');

        $value = is_string($key) && $key !== '' ? $config->get($key, $this) : $config->all();
        if ($value === $this) {
            throw new UsageException(sprintf('There is no configuration value "%s".', $key));
        }

        $output->writeln((string) json_encode(self::mask($value, is_string($key) ? $key : ''), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));

        return self::SUCCESS;
    }

    private static function mask(mixed $value, string $key): mixed
    {
        if (is_array($value)) {
            $masked = [];
            foreach ($value as $name => $item) {
                $masked[$name] = self::mask($item, (string) $name);
            }

            return $masked;
        }

        if ($value instanceof Closure) {
            return 'Closure';
        }

        if (is_object($value)) {
            return $value::class;
        }

        return $value !== null && $value !== '' && preg_match(self::SECRET_KEYS, $key) === 1 ? '********' : $value;
    }
}
