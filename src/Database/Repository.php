<?php

declare(strict_types=1);

namespace WpAlchemist\Framework\Database;

use InvalidArgumentException;
use RuntimeException;
use wpdb;

/**
 * Small, safe CRUD layer over one custom table.
 *
 * Values always go through $wpdb placeholders, and identifiers (table and
 * column names) through %i, so nothing taken from a request can end up as raw SQL.
 *
 *     final class ActivityLog extends Repository
 *     {
 *         protected string $table = 'activity_log';
 *     }
 */
abstract class Repository
{
    /** Table name without the WordPress and plugin prefixes. */
    protected string $table;

    protected string $primaryKey = 'id';

    public function __construct(protected readonly wpdb $db, protected readonly string $prefix)
    {
    }

    public function table(): string
    {
        return $this->db->prefix . $this->prefix . '_' . $this->table;
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $row = $this->db->get_row(
            $this->db->prepare('SELECT * FROM %i WHERE %i = %d', $this->table(), $this->primaryKey, $id),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, scalar|null> $where Column => value, joined with AND.
     * @return list<array<string, mixed>>
     */
    public function where(array $where = [], int $limit = 50, int $offset = 0, string $orderBy = 'id', string $direction = 'DESC'): array
    {
        [$clause, $params] = $this->compileWhere($where);
        $direction = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "SELECT * FROM %i{$clause} ORDER BY %i {$direction} LIMIT %d OFFSET %d";
        $args = [$this->table(), ...$params, self::column($orderBy), max(1, $limit), max(0, $offset)];

        return $this->db->get_results($this->db->prepare($sql, ...$args), ARRAY_A) ?: [];
    }

    /** @param array<string, scalar|null> $where */
    public function count(array $where = []): int
    {
        [$clause, $params] = $this->compileWhere($where);

        return (int) $this->db->get_var($this->db->prepare("SELECT COUNT(*) FROM %i{$clause}", $this->table(), ...$params));
    }

    /**
     * @param array<string, scalar|null> $where
     * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public function paginate(int $page = 1, int $perPage = 20, array $where = []): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        return [
            'items' => $this->where($where, $perPage, ($page - 1) * $perPage),
            'total' => $this->count($where),
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /** @param array<string, scalar|null> $data */
    public function insert(array $data): int
    {
        $this->assertColumns(array_keys($data));

        if ($this->db->insert($this->table(), $data) === false) {
            throw new RuntimeException("Insert into {$this->table()} failed: {$this->db->last_error}");
        }

        return (int) $this->db->insert_id;
    }

    /** @param array<string, scalar|null> $data */
    public function update(int $id, array $data): bool
    {
        $this->assertColumns(array_keys($data));

        return $this->db->update($this->table(), $data, [$this->primaryKey => $id]) !== false;
    }

    public function delete(int $id): bool
    {
        return (bool) $this->db->delete($this->table(), [$this->primaryKey => $id]);
    }

    /**
     * @param array<string, scalar|null> $where
     * @return array{0: string, 1: list<scalar|null>}
     */
    protected function compileWhere(array $where): array
    {
        if ($where === []) {
            return ['', []];
        }

        $parts = [];
        $params = [];

        foreach ($where as $column => $value) {
            $params[] = self::column((string) $column);

            if ($value === null) {
                $parts[] = '%i IS NULL';
                continue;
            }

            $parts[] = '%i = ' . (is_int($value) ? '%d' : (is_float($value) ? '%f' : '%s'));
            $params[] = $value;
        }

        return [' WHERE ' . implode(' AND ', $parts), $params];
    }

    /** @param list<string|int> $columns */
    private function assertColumns(array $columns): void
    {
        foreach ($columns as $column) {
            self::column((string) $column);
        }
    }

    private static function column(string $column): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/', $column)) {
            throw new InvalidArgumentException("Invalid column name: {$column}");
        }

        return $column;
    }
}
