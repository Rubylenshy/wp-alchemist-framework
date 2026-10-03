<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

if (!defined('ABSPATH')) {
    define('ABSPATH', sys_get_temp_dir() . '/wp-alchemist-tests/');
}
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

require __DIR__ . '/stubs/wordpress.php';
