<?php

declare(strict_types=1);

namespace WpAlchemist\Framework\Database;

use RuntimeException;

/**
 * Runs migration files in filename order and records each one once it succeeds.
 *
 * Name files with a sortable timestamp: 2026_10_03_000000_create_items_table.php.
 * Applied names are stored per site in a single option. A short-lived lock
 * stops two concurrent requests from running the same migration twice.
 */
final class Migrator
{
    private const LOCK_TTL = 300;

    public function __construct(
        private readonly string $path,
        private readonly string $optionKey,
        private readonly Schema $schema,
    ) {
    }

    /** @return list<string> Migration names that have not run yet, in order. */
    public function pending(): array
    {
        return array_values(array_diff($this->files(), $this->applied()));
    }

    /** @return list<string> */
    public function applied(): array
    {
        $applied = get_option($this->optionKey, []);

        return is_array($applied) ? array_values($applied) : [];
    }

    /**
     * Run every pending migration.
     *
     * @return list<string> Names that ran. Empty when nothing was pending or another request holds the lock.
     */
    public function migrate(): array
    {
        $pending = $this->pending();

        if ($pending === [] || !$this->acquireLock()) {
            return [];
        }

        $ran = [];

        try {
            foreach ($pending as $name) {
                $this->load($name)->up($this->schema);

                $ran[] = $name;
                update_option($this->optionKey, array_merge($this->applied(), [$name]), false);
            }
        } finally {
            $this->releaseLock();
        }

        return $ran;
    }

    /** Roll back every applied migration in reverse order. Used on uninstall. */
    public function reset(): void
    {
        $known = array_flip($this->files());

        foreach (array_reverse($this->applied()) as $name) {
            if (isset($known[$name])) {
                $this->load($name)->down($this->schema);
            }
        }

        delete_option($this->optionKey);
    }

    /** @return list<string> */
    private function files(): array
    {
        if (!is_dir($this->path)) {
            return [];
        }

        $files = glob(rtrim($this->path, '/') . '/*.php') ?: [];
        $names = array_map(static fn (string $file): string => basename($file, '.php'), $files);
        sort($names, SORT_STRING);

        return $names;
    }

    private function load(string $name): Migration
    {
        $migration = require rtrim($this->path, '/') . '/' . $name . '.php';

        if (!$migration instanceof Migration) {
            throw new RuntimeException("Migration {$name} must return an instance of " . Migration::class . '.');
        }

        return $migration;
    }

    private function acquireLock(): bool
    {
        $lock = $this->optionKey . '_lock';

        if (add_option($lock, time(), '', false)) {
            return true;
        }

        // Recover from a lock left behind by a request that died mid-migration.
        if ((int) get_option($lock) < time() - self::LOCK_TTL) {
            delete_option($lock);
            return add_option($lock, time(), '', false);
        }

        return false;
    }

    private function releaseLock(): void
    {
        delete_option($this->optionKey . '_lock');
    }
}
