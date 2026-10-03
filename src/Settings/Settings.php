<?php

declare(strict_types=1);

namespace WpAlchemist\Framework\Settings;

use InvalidArgumentException;
use WpAlchemist\Framework\Http\ValidationException;

/**
 * Schema-driven settings stored in a single option.
 *
 * The schema is the single source of truth: it supplies defaults, rejects
 * unknown keys, validates and casts values, and generates the JSON schema for
 * REST route args.
 *
 *     new Settings('my_plugin_settings', [
 *         'enabled'  => ['type' => 'boolean', 'default' => true],
 *         'greeting' => ['type' => 'string', 'default' => 'Hi', 'maxLength' => 120],
 *         'per_page' => ['type' => 'integer', 'default' => 10, 'minimum' => 1, 'maximum' => 100],
 *         'mode'     => ['type' => 'string', 'default' => 'auto', 'enum' => ['auto', 'manual']],
 *     ]);
 *
 * Supported types: boolean, integer, number, string, array (list of strings).
 * Add 'sanitize' => callable to post-process a value after validation.
 *
 * @phpstan-type Field array{type: string, default: mixed, enum?: list<scalar>, minimum?: int|float, maximum?: int|float, maxLength?: int, sanitize?: callable, description?: string}
 */
final class Settings
{
    private const TYPES = ['boolean', 'integer', 'number', 'string', 'array'];

    /** @param array<string, Field> $schema */
    public function __construct(private readonly string $optionKey, private readonly array $schema)
    {
        foreach ($schema as $key => $field) {
            if (!in_array($field['type'] ?? null, self::TYPES, true) || !array_key_exists('default', $field)) {
                throw new InvalidArgumentException("Setting \"{$key}\" needs a supported type and a default.");
            }
        }
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        $stored = get_option($this->optionKey, []);
        $stored = is_array($stored) ? array_intersect_key($stored, $this->schema) : [];

        return array_merge($this->defaults(), $stored);
    }

    public function get(string $key): mixed
    {
        if (!isset($this->schema[$key])) {
            throw new InvalidArgumentException("Unknown setting \"{$key}\".");
        }

        return $this->all()[$key];
    }

    /**
     * Validate and save a partial update. Nothing is saved if any field fails.
     *
     * @param array<string, mixed> $values
     * @return array<string, mixed> All settings after the update.
     * @throws ValidationException
     */
    public function update(array $values): array
    {
        $errors = [];
        $clean = [];

        foreach ($values as $key => $value) {
            if (!isset($this->schema[$key])) {
                $errors[$key] = 'Unknown setting.';
                continue;
            }

            $result = $this->cast($this->schema[$key], $value);

            if ($result instanceof Invalid) {
                $errors[$key] = $result->message;
                continue;
            }

            $clean[$key] = $result;
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $settings = array_merge($this->all(), $clean);
        update_option($this->optionKey, $settings);

        return $settings;
    }

    /** @return array<string, mixed> */
    public function defaults(): array
    {
        return array_map(static fn (array $field): mixed => $field['default'], $this->schema);
    }

    /**
     * JSON schema for REST route args. Every field is optional so partial
     * updates work; WordPress validates types before the handler runs.
     *
     * @return array<string, array<string, mixed>>
     */
    public function restArgs(): array
    {
        $args = [];

        foreach ($this->schema as $key => $field) {
            $arg = array_intersect_key($field, array_flip(['type', 'enum', 'minimum', 'maximum', 'maxLength', 'description']));

            if ($field['type'] === 'array') {
                $arg['items'] = ['type' => 'string'];
            }

            $args[$key] = $arg + ['required' => false];
        }

        return $args;
    }

    public function delete(): void
    {
        delete_option($this->optionKey);
    }

    /** @param Field $field */
    private function cast(array $field, mixed $value): mixed
    {
        $value = match ($field['type']) {
            'boolean' => $this->toBoolean($value),
            'integer' => is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', $value))
                ? (int) $value
                : new Invalid('Must be a whole number.'),
            'number' => is_numeric($value) ? (float) $value : new Invalid('Must be a number.'),
            'string' => is_scalar($value) ? sanitize_text_field((string) $value) : new Invalid('Must be text.'),
            'array' => is_array($value)
                ? array_values(array_map(static fn ($v): string => sanitize_text_field((string) $v), array_filter($value, 'is_scalar')))
                : new Invalid('Must be a list.'),
        };

        if ($value instanceof Invalid) {
            return $value;
        }

        if (isset($field['enum']) && !in_array($value, $field['enum'], true)) {
            return new Invalid('Must be one of: ' . implode(', ', array_map('strval', $field['enum'])) . '.');
        }

        if (is_int($value) || is_float($value)) {
            if (isset($field['minimum']) && $value < $field['minimum']) {
                return new Invalid("Must be at least {$field['minimum']}.");
            }
            if (isset($field['maximum']) && $value > $field['maximum']) {
                return new Invalid("Must be at most {$field['maximum']}.");
            }
        }

        if (is_string($value) && isset($field['maxLength']) && mb_strlen($value) > $field['maxLength']) {
            return new Invalid("Must be {$field['maxLength']} characters or fewer.");
        }

        return isset($field['sanitize']) ? ($field['sanitize'])($value) : $value;
    }

    private function toBoolean(mixed $value): bool|Invalid
    {
        if (is_bool($value)) {
            return $value;
        }

        $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $bool ?? new Invalid('Must be true or false.');
    }
}
