<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Bootstrap;

use EchoFusion\ConfigManager\Config;
use EchoFusion\Framework\Exceptions\ConfigurationException;
use function basename;
use function glob;
use function is_array;
use function is_callable;
use function is_dir;
use function is_file;
use function ksort;
use function rtrim;
use function sprintf;

/**
 * Reads the `config` directory of an application.
 *
 * Every `config/<name>.php` returns an array and becomes the section `<name>`: `config/database.php` is read
 * as `database`. A file `config/environments/<environment>.php` may return a partial array of sections that is
 * merged on top, so production and development can differ without copying whole files.
 */
final class ConfigLoader
{
    /**
     * @return array<string, mixed> the sections
     *
     * @throws ConfigurationException when the directory is missing or a file does not return an array
     */
    public static function load(string $configPath, string $environment): array
    {
        $configPath = rtrim($configPath, '/\\');
        if (!is_dir($configPath)) {
            throw new ConfigurationException(sprintf('The config directory "%s" does not exist.', $configPath));
        }

        $sections = [];
        foreach (glob($configPath . '/*.php') ?: [] as $file) {
            $sections[basename($file, '.php')] = self::read($file, true);
        }
        ksort($sections);

        $overrideFile = sprintf('%s/environments/%s.php', $configPath, $environment);
        $override = is_file($overrideFile) ? self::read($overrideFile, false) : [];

        return (new Config($sections, $override))->all();
    }

    /**
     * @return array<array-key, mixed>|callable
     */
    private static function read(string $file, bool $mayBeCallable): mixed
    {
        $value = (static fn (string $path): mixed => require $path)($file);

        if (is_array($value) || ($mayBeCallable && is_callable($value))) {
            return $value;
        }

        throw new ConfigurationException(sprintf('The config file "%s" must return an array%s.', $file, $mayBeCallable ? ' (or a function, for routes)' : ''));
    }
}
