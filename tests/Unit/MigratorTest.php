<?php

declare(strict_types=1);

namespace WpAlchemist\Framework\Tests\Unit;

use RuntimeException;
use WpAlchemist\Framework\Database\Migrator;
use WpAlchemist\Framework\Database\Schema;
use WpAlchemist\Framework\Tests\TestCase;
use wpdb;

final class MigratorTest extends TestCase
{
    private string $dir;

    /** @var list<string> Filled by the fixture migrations. */
    public static array $log = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeOptions();
        self::$log = [];

        $this->dir = sys_get_temp_dir() . '/alchemist-migrations-' . uniqid();
        mkdir($this->dir);

        // Written out of order on purpose: the migrator must sort by name.
        $this->migration('2026_02_01_000000_second');
        $this->migration('2026_01_01_000000_first');
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*.php') ?: []);
        rmdir($this->dir);
        parent::tearDown();
    }

    public function testRunsPendingMigrationsInOrderAndRecordsThem(): void
    {
        $migrator = $this->migrator();

        $this->assertSame(['2026_01_01_000000_first', '2026_02_01_000000_second'], $migrator->pending());
        $this->assertSame(['2026_01_01_000000_first', '2026_02_01_000000_second'], $migrator->migrate());
        $this->assertSame(['up:2026_01_01_000000_first', 'up:2026_02_01_000000_second'], self::$log);
        $this->assertSame([], $migrator->pending());
        $this->assertArrayNotHasKey('demo_migrations_lock', $this->options, 'Lock must be released.');
    }

    public function testOnlyRunsNewMigrations(): void
    {
        $this->options['demo_migrations'] = ['2026_01_01_000000_first'];

        $this->assertSame(['2026_02_01_000000_second'], $this->migrator()->migrate());
    }

    public function testSkipsWhileAnotherRequestHoldsTheLock(): void
    {
        $this->options['demo_migrations_lock'] = time();

        $this->assertSame([], $this->migrator()->migrate());
        $this->assertSame([], self::$log);
    }

    public function testRecoversAStaleLock(): void
    {
        $this->options['demo_migrations_lock'] = time() - 3600;

        $this->assertCount(2, $this->migrator()->migrate());
    }

    public function testRecordsProgressWhenAMigrationFails(): void
    {
        file_put_contents($this->dir . '/2026_03_01_000000_broken.php', '<?php return new stdClass();');

        try {
            $this->migrator()->migrate();
            $this->fail('Expected a RuntimeException.');
        } catch (RuntimeException) {
        }

        $this->assertSame(['2026_01_01_000000_first', '2026_02_01_000000_second'], $this->options['demo_migrations']);
        $this->assertArrayNotHasKey('demo_migrations_lock', $this->options);
    }

    public function testResetRollsBackInReverseOrder(): void
    {
        $migrator = $this->migrator();
        $migrator->migrate();
        self::$log = [];

        $migrator->reset();

        $this->assertSame(['down:2026_02_01_000000_second', 'down:2026_01_01_000000_first'], self::$log);
        $this->assertArrayNotHasKey('demo_migrations', $this->options);
    }

    private function migrator(): Migrator
    {
        return new Migrator($this->dir, 'demo_migrations', new Schema(new wpdb(), 'demo'));
    }

    private function migration(string $name): void
    {
        $test = self::class;
        file_put_contents($this->dir . "/{$name}.php", <<<PHP
            <?php
            return new class extends \WpAlchemist\Framework\Database\Migration {
                public function up(\WpAlchemist\Framework\Database\Schema \$schema): void { \\{$test}::\$log[] = 'up:{$name}'; }
                public function down(\WpAlchemist\Framework\Database\Schema \$schema): void { \\{$test}::\$log[] = 'down:{$name}'; }
            };
            PHP);
    }
}
