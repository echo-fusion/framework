<?php

declare(strict_types=1);

namespace EchoFusion\Framework;

use function array_key_exists;
use function function_exists;
use function getenv;
use function is_string;
use function strtolower;

if (!function_exists(__NAMESPACE__ . '\\env')) {
    /**
     * Read an environment variable (from the real environment or from `.env`), with a default.
     *
     * The strings "true", "false" and "null" (any case) become `true`, `false` and `null`; "empty" becomes "".
     * Use it in config files, never in application code: config is the one place that knows about the environment.
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = array_key_exists($key, $_ENV) ? $_ENV[$key] : ($_SERVER[$key] ?? getenv($key));

        if ($value === false || $value === null) {
            return $default;
        }

        if (!is_string($value)) {
            return $value;
        }

        return match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $value,
        };
    }
}
