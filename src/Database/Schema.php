<?php

declare(strict_types=1);

namespace WpAlchemist\Framework\Database;

use RuntimeException;
use wpdb;

/**
 * Thin wrapper around dbDelta() that handles table prefixes and charset.
 *
 * Table names are always built from code, never from request input.
 */
class Schema
{
    public function __construct(private readonly wpdb $db, private readonly string $prefix)
    {
    }

    /** Full table name, e.g. "wp_my_plugin_activity_log". Reads $wpdb->prefix at call time so it follows switch_to_blog(). */
    public function table(string $name): string
    {
        return $this->db->prefix . $this->prefix . '_' . $name;
    }

    /**
     * Create or update a table. dbDelta() is strict about formatting: one column
     * per line, and two spaces after PRIMARY KEY.
     */
    public function create(string $name, string $columns): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = $this->table($name);
        $charset = $this->db->get_charset_collate();

        dbDelta("CREATE TABLE {$table} (\n" . trim($columns) . "\n) {$charset};");

        if ($this->db->last_error !== '') {
            throw new RuntimeException("Creating table {$table} failed: {$this->db->last_error}");
        }
    }

    public function drop(string $name): void
    {
        $this->db->query($this->db->prepare('DROP TABLE IF EXISTS %i', $this->table($name)));
    }

    public function db(): wpdb
    {
        return $this->db;
    }
}
