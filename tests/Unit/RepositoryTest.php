<?php

declare(strict_types=1);

namespace WpAlchemist\Framework\Tests\Unit;

use InvalidArgumentException;
use WpAlchemist\Framework\Database\Repository;
use WpAlchemist\Framework\Tests\TestCase;
use wpdb;

final class RepositoryTest extends TestCase
{
    private wpdb $db;
    private Repository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = new wpdb();
        $this->repo = new class ($this->db, 'demo') extends Repository {
            protected string $table = 'items';
        };
    }

    public function testBuildsPrefixedTableName(): void
    {
        $this->assertSame('wp_demo_items', $this->repo->table());
    }

    public function testWhereUsesPlaceholdersForIdentifiersAndValues(): void
    {
        $this->db->nextResult = [['id' => 1]];

        $rows = $this->repo->where(['status' => 'open', 'user_id' => 3, 'deleted_at' => null], 10, 20, 'created_at', 'asc');

        $this->assertSame([['id' => 1]], $rows);
        [$fn, $args] = $this->db->calls[0];
        $this->assertSame('prepare', $fn);
        $this->assertSame(
            'SELECT * FROM %i WHERE %i = %s AND %i = %d AND %i IS NULL ORDER BY %i ASC LIMIT %d OFFSET %d',
            $args[0]
        );
        $this->assertSame(['wp_demo_items', 'status', 'open', 'user_id', 3, 'deleted_at', 'created_at', 10, 20], array_slice($args, 1));
    }

    public function testRejectsColumnNamesThatCouldInjectSql(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->repo->where(['status` = 1 OR 1=1 -- ' => 'x']);
    }

    public function testRejectsUnsafeOrderBy(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->repo->where([], 10, 0, 'id; DROP TABLE wp_users');
    }

    public function testInsertReturnsNewId(): void
    {
        $this->assertSame(42, $this->repo->insert(['title' => 'Hello']));
        $this->assertSame(['insert', ['wp_demo_items', ['title' => 'Hello']]], $this->db->calls[0]);
    }

    public function testPaginateClampsInput(): void
    {
        $this->db->nextResult = [];

        $page = $this->repo->paginate(0, 1000);

        $this->assertSame(1, $page['page']);
        $this->assertSame(100, $page['per_page']);
    }
}
