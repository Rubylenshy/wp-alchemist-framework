<?php

declare(strict_types=1);

namespace WpAlchemist\Framework\Support;

/**
 * String case conversion and small helpers that PHP and WordPress do not provide.
 */
final class Str
{
    /** "my plugin-name" => ["my", "plugin", "name"] */
    public static function words(string $value): array
    {
        $value = (string) preg_replace('/([a-z\d])([A-Z])/', '$1 $2', $value);
        $value = (string) preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1 $2', $value);
        $parts = preg_split('/[^A-Za-z\d]+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_map('strtolower', $parts);
    }

    /** "my plugin-name" => "MyPluginName" */
    public static function studly(string $value): string
    {
        return implode('', array_map('ucfirst', self::words($value)));
    }

    /** "my plugin-name" => "myPluginName" */
    public static function camel(string $value): string
    {
        return lcfirst(self::studly($value));
    }

    /** "MyPluginName" => "my_plugin_name" */
    public static function snake(string $value): string
    {
        return implode('_', self::words($value));
    }

    /** "MyPluginName" => "my-plugin-name" */
    public static function kebab(string $value): string
    {
        return implode('-', self::words($value));
    }

    /** "MY_PLUGIN_NAME" style, for constants. */
    public static function constant(string $value): string
    {
        return strtoupper(self::snake($value));
    }

    /** Truncate to $limit characters (multibyte-safe), appending $end when cut. */
    public static function limit(string $value, int $limit, string $end = '…'): string
    {
        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, $limit)) . $end;
    }

    /** Text before the first occurrence of $search, or the whole string. */
    public static function before(string $value, string $search): string
    {
        $pos = $search === '' ? false : strpos($value, $search);

        return $pos === false ? $value : substr($value, 0, $pos);
    }

    /** Text after the first occurrence of $search, or the whole string. */
    public static function after(string $value, string $search): string
    {
        $pos = $search === '' ? false : strpos($value, $search);

        return $pos === false ? $value : substr($value, $pos + strlen($search));
    }
}
