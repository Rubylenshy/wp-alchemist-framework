<?php

// Minimal stand-ins for the WordPress classes the framework type-hints against.
// Functions are mocked per test with Brain Monkey.

// phpcs:ignoreFile

class WP_Error
{
    public function __construct(public string $code = '', public string $message = '', public mixed $data = '')
    {
    }

    public function get_error_code(): string
    {
        return $this->code;
    }

    public function get_error_data(): mixed
    {
        return $this->data;
    }
}

class WP_REST_Request
{
    public function __construct(private array $params = [])
    {
    }

    public function get_param(string $key): mixed
    {
        return $this->params[$key] ?? null;
    }

    public function get_json_params(): array
    {
        return $this->params;
    }
}

class WP_REST_Response
{
    public function __construct(public mixed $data = null, public int $status = 200)
    {
    }
}

/** Records every call so tests can assert on the generated SQL and arguments. */
class wpdb
{
    public string $prefix = 'wp_';
    public string $last_error = '';
    public int $insert_id = 0;
    /** @var list<array{0: string, 1: array}> */
    public array $calls = [];
    public mixed $nextResult = null;

    public function prepare(string $query, mixed ...$args): string
    {
        $this->calls[] = ['prepare', [$query, ...$args]];
        return $query . ' ' . json_encode($args);
    }

    public function __call(string $name, array $args): mixed
    {
        $this->calls[] = [$name, $args];
        if ($name === 'insert') {
            $this->insert_id = 42;
            return 1;
        }
        if ($name === 'get_charset_collate') {
            return 'DEFAULT CHARSET=utf8mb4';
        }
        return $this->nextResult;
    }
}
