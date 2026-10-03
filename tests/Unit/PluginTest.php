<?php

declare(strict_types=1);

namespace WpAlchemist\Framework\Tests\Unit;

use Brain\Monkey\Functions;
use WpAlchemist\Framework\Config;
use WpAlchemist\Framework\Plugin;
use WpAlchemist\Framework\Tests\TestCase;
use wpdb;

final class PluginTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeOptions();
        $GLOBALS['wpdb'] = new wpdb();

        $this->dir = sys_get_temp_dir() . '/alchemist-plugin-' . uniqid();
        mkdir($this->dir . '/database/migrations', 0777, true);
        file_put_contents(
            $this->dir . '/database/migrations/2026_01_01_000000_noop.php',
            '<?php return new class extends \WpAlchemist\Framework\Database\Migration {'
            . ' public function up(\WpAlchemist\Framework\Database\Schema $schema): void {} };'
        );
    }

    protected function tearDown(): void
    {
        unlink($this->dir . '/database/migrations/2026_01_01_000000_noop.php');
        rmdir($this->dir . '/database/migrations');
        rmdir($this->dir . '/database');
        rmdir($this->dir);
        unset($GLOBALS['wpdb']);
        parent::tearDown();
    }

    public function testMigratesAndStoresVersionWhenVersionChanged(): void
    {
        Functions\when('wp_get_environment_type')->justReturn('production');
        $this->options['demo_version'] = '0.9.0';

        $this->plugin()->maybeUpgrade();

        $this->assertSame('1.0.0', $this->options['demo_version']);
        $this->assertSame(['2026_01_01_000000_noop'], $this->options['demo_migrations']);
    }

    public function testIgnoresNewMigrationFilesInProductionUntilTheVersionChanges(): void
    {
        Functions\when('wp_get_environment_type')->justReturn('production');
        $this->options['demo_version'] = '1.0.0';

        $this->plugin()->maybeUpgrade();

        $this->assertArrayNotHasKey('demo_migrations', $this->options);
    }

    public function testRunsNewMigrationFilesDuringDevelopment(): void
    {
        Functions\when('wp_get_environment_type')->justReturn('local');
        $this->options['demo_version'] = '1.0.0';

        $this->plugin()->maybeUpgrade();

        $this->assertSame(['2026_01_01_000000_noop'], $this->options['demo_migrations']);
    }

    private function plugin(): Plugin
    {
        $config = new Config($this->dir . '/demo.php', 'demo', '1.0.0', 'demo', 'demo', 'demo/v1');

        return new class ($config) extends Plugin {
            protected function modules(): array
            {
                return [];
            }
        };
    }
}
