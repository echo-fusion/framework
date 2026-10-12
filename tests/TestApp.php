<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Tests;

use EchoFusion\Framework\Application;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use function dirname;
use function file_put_contents;
use function is_array;
use function is_dir;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;
use function var_export;

/**
 * Builds a throw-away application directory from the config files a test needs.
 */
final class TestApp
{
    /**
     * @var list<string>
     */
    private static array $directories = [];

    /**
     * @param array<string, array<array-key, mixed>|string> $config file name (without .php) => the array to return, or raw PHP code
     * @param array<string, string> $files other files, relative to the application directory
     */
    public static function create(array $config = [], array $files = [], ?string $environment = 'testing'): Application
    {
        return new Application(self::directory($config, $files), $environment);
    }

    /**
     * @param array<string, array<array-key, mixed>|string> $config
     * @param array<string, string> $files
     */
    public static function directory(array $config = [], array $files = []): string
    {
        $directory = sys_get_temp_dir() . '/ef-framework-' . uniqid('', true);
        mkdir($directory . '/config', 0775, true);
        self::$directories[] = $directory;

        $config += ['app' => ['debug' => true]];
        foreach ($config as $name => $value) {
            $path = $directory . '/config/' . $name . '.php';
            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0775, true);
            }
            file_put_contents($path, is_array($value) ? '<?php return ' . var_export($value, true) . ';' : $value);
        }

        foreach ($files as $relative => $contents) {
            $path = $directory . '/' . $relative;
            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0775, true);
            }
            file_put_contents($path, $contents);
        }

        return $directory;
    }

    public static function cleanUp(): void
    {
        foreach (self::$directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $item) {
                $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }
            rmdir($directory);
        }

        self::$directories = [];
    }
}
