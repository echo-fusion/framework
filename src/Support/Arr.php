<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Support;

use stdClass;
use function array_key_exists;
use function array_pop;
use function explode;
use function is_array;

/**
 * Reading nested arrays with "dot" keys: `Arr::get($data, 'user.address.city')`.
 *
 * A key that exists as written wins over the dotted reading (`['a.b' => 1]` is found by `a.b`).
 */
final class Arr
{
    /**
     * @param array<array-key, mixed> $array
     */
    public static function get(array $array, string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        $value = $array;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $array
     */
    public static function has(array $array, string $key): bool
    {
        $missing = new stdClass();

        return self::get($array, $key, $missing) !== $missing;
    }

    /**
     * @param array<array-key, mixed> $array
     * @param list<string> $keys
     *
     * @return array<array-key, mixed> only the given keys that exist (dotted keys are kept nested)
     */
    public static function only(array $array, array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            if (self::has($array, $key)) {
                self::set($result, $key, self::get($array, $key));
            }
        }

        return $result;
    }

    /**
     * @param array<array-key, mixed> $array
     * @param list<string> $keys
     *
     * @return array<array-key, mixed>
     */
    public static function except(array $array, array $keys): array
    {
        foreach ($keys as $key) {
            self::forget($array, $key);
        }

        return $array;
    }

    /**
     * @param array<array-key, mixed> $array
     */
    public static function set(array &$array, string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $last = array_pop($segments);
        $current = &$array;

        foreach ($segments as $segment) {
            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                $current[$segment] = [];
            }
            $current = &$current[$segment];
        }

        $current[$last] = $value;
    }

    /**
     * @param array<array-key, mixed> $array
     */
    private static function forget(array &$array, string $key): void
    {
        if (array_key_exists($key, $array)) {
            unset($array[$key]);

            return;
        }

        $segments = explode('.', $key);
        $last = array_pop($segments);
        $current = &$array;

        foreach ($segments as $segment) {
            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                return;
            }
            $current = &$current[$segment];
        }

        unset($current[$last]);
    }
}
