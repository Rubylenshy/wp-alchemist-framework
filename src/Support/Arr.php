<?php

declare(strict_types=1);

namespace WpAlchemist\Framework\Support;

/**
 * Array helpers with dot-notation access.
 */
final class Arr
{
    /**
     * Arr::get($data, 'user.address.city', 'n/a')
     *
     * @param array<array-key, mixed> $array
     */
    public static function get(array $array, string|int|null $key, mixed $default = null): mixed
    {
        if ($key === null) {
            return $array;
        }

        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        foreach (explode('.', (string) $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }

        return $array;
    }

    /** @param array<array-key, mixed> $array */
    public static function has(array $array, string $key): bool
    {
        $missing = new \stdClass();

        return self::get($array, $key, $missing) !== $missing;
    }

    /**
     * Set a value using dot notation, creating nested arrays as needed.
     *
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public static function set(array $array, string $key, mixed $value): array
    {
        $segments = explode('.', $key);
        $current = &$array;

        foreach ($segments as $i => $segment) {
            if ($i === count($segments) - 1) {
                $current[$segment] = $value;
                break;
            }
            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                $current[$segment] = [];
            }
            $current = &$current[$segment];
        }

        return $array;
    }

    /**
     * @param array<array-key, mixed> $array
     * @param list<array-key>         $keys
     * @return array<array-key, mixed>
     */
    public static function only(array $array, array $keys): array
    {
        return array_intersect_key($array, array_flip($keys));
    }

    /**
     * @param array<array-key, mixed> $array
     * @param list<array-key>         $keys
     * @return array<array-key, mixed>
     */
    public static function except(array $array, array $keys): array
    {
        return array_diff_key($array, array_flip($keys));
    }

    /**
     * Pluck a field from a list of arrays or objects.
     *
     * @param iterable<array<array-key, mixed>|object> $items
     * @return list<mixed>
     */
    public static function pluck(iterable $items, string $field): array
    {
        $values = [];

        foreach ($items as $item) {
            $values[] = is_object($item) ? ($item->{$field} ?? null) : self::get($item, $field);
        }

        return $values;
    }

    /** @return array<array-key, mixed> */
    public static function wrap(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        return is_array($value) ? $value : [$value];
    }
}
