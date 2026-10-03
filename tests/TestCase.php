<?php

declare(strict_types=1);

namespace WpAlchemist\Framework\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** @var array<string, mixed> In-memory wp_options table. */
    protected array $options = [];

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        $this->options = [];
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    /** Back get/add/update/delete_option with $this->options. */
    protected function fakeOptions(): void
    {
        Functions\when('get_option')->alias(fn (string $key, mixed $default = false) => $this->options[$key] ?? $default);
        Functions\when('update_option')->alias(function (string $key, mixed $value): bool {
            $this->options[$key] = $value;
            return true;
        });
        Functions\when('add_option')->alias(function (string $key, mixed $value = ''): bool {
            if (array_key_exists($key, $this->options)) {
                return false;
            }
            $this->options[$key] = $value;
            return true;
        });
        Functions\when('delete_option')->alias(function (string $key): bool {
            unset($this->options[$key]);
            return true;
        });
    }
}
