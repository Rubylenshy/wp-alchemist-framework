<?php

declare(strict_types=1);

namespace WpAlchemist\Framework\Tests\Unit;

use Brain\Monkey\Functions;
use RuntimeException;
use WpAlchemist\Framework\Assets\Vite;
use WpAlchemist\Framework\Config;
use WpAlchemist\Framework\Tests\TestCase;

final class ViteTest extends TestCase
{
    private string $dir;

    /** @var list<array{0: string, 1: list<mixed>}> */
    private array $calls = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/alchemist-vite-' . uniqid();
        mkdir($this->dir . '/build/.vite', 0777, true);

        Functions\when('plugin_dir_url')->justReturn('https://example.test/wp-content/plugins/demo/');
        Functions\when('wp_json_encode')->alias('json_encode');
        foreach (['wp_enqueue_script', 'wp_enqueue_style', 'wp_add_inline_script', 'add_filter'] as $fn) {
            Functions\when($fn)->alias(function (...$args) use ($fn): bool {
                $this->calls[] = [$fn, $args];
                return true;
            });
        }
    }

    protected function tearDown(): void
    {
        @unlink($this->dir . '/build/hot');
        @unlink($this->dir . '/build/.vite/manifest.json');
        rmdir($this->dir . '/build/.vite');
        rmdir($this->dir . '/build');
        rmdir($this->dir);
        parent::tearDown();
    }

    public function testEnqueuesBuiltEntryWithCssFromImportedChunks(): void
    {
        $this->manifest([
            'src/Modules/Settings/resources/main.js' => [
                'file' => 'assets/main-abc.js',
                'css' => ['assets/main-abc.css'],
                'imports' => ['_vendor.js'],
                'isEntry' => true,
            ],
            '_vendor.js' => ['file' => 'assets/vendor-123.js', 'css' => ['assets/vendor-123.css'], 'imports' => ['src/Modules/Settings/resources/main.js']],
        ]);

        $handle = $this->vite()->enqueue('src/Modules/Settings/resources/main.js', 'demoApp', ['nonce' => 'n']);

        $this->assertSame('demo-src-modules-settings-resources-main', $handle);
        $this->assertSame(
            [$handle, 'https://example.test/wp-content/plugins/demo/build/assets/main-abc.js', [], null, ['in_footer' => true]],
            $this->call('wp_enqueue_script')[1]
        );
        $styles = array_map(fn ($c) => $c[1][1], $this->calls('wp_enqueue_style'));
        $this->assertSame([
            'https://example.test/wp-content/plugins/demo/build/assets/main-abc.css',
            'https://example.test/wp-content/plugins/demo/build/assets/vendor-123.css',
        ], $styles);
        $this->assertSame([$handle, 'window.demoApp = {"nonce":"n"};', 'before'], $this->call('wp_add_inline_script')[1]);
        $this->assertCount(1, $this->calls('add_filter'), 'script_loader_tag filter is added once.');
    }

    public function testUsesDevServerWhenHotFileExists(): void
    {
        file_put_contents($this->dir . '/build/hot', "http://localhost:5173\n");

        $this->vite()->enqueue('src/main.js');

        $scripts = array_map(fn ($c) => $c[1][1], $this->calls('wp_enqueue_script'));
        $this->assertSame(['http://localhost:5173/@vite/client', 'http://localhost:5173/src/main.js'], $scripts);
        $this->assertSame([], $this->calls('wp_enqueue_style'));
    }

    public function testMissingEntryExplainsHowToFixIt(): void
    {
        $this->manifest([]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('npm run build');

        $this->vite()->enqueue('src/missing.js');
    }

    public function testAddsModuleTypeOnlyToOwnExternalScript(): void
    {
        $this->manifest(['src/main.js' => ['file' => 'main.js']]);
        $vite = $this->vite();
        $handle = $vite->enqueue('src/main.js');

        $tag = "<script id=\"{$handle}-js-before\">window.x = 1;</script>\n"
            . "<script type='text/javascript' src='https://example.test/main.js' id='{$handle}-js'></script>";

        $out = $vite->addModuleType($tag, $handle);

        $this->assertStringContainsString("<script id=\"{$handle}-js-before\">", $out);
        $this->assertStringContainsString("<script type=\"module\" src='https://example.test/main.js'", $out);
        $this->assertStringNotContainsString('text/javascript', $out);
        $this->assertSame('<script src="x.js"></script>', $vite->addModuleType('<script src="x.js"></script>', 'jquery'));
    }

    private function vite(): Vite
    {
        return new Vite(new Config($this->dir . '/demo.php', 'demo', '1.0.0', 'demo', 'demo', 'demo/v1'));
    }

    private function manifest(array $manifest): void
    {
        file_put_contents($this->dir . '/build/.vite/manifest.json', json_encode($manifest));
    }

    private function calls(string $fn): array
    {
        return array_values(array_filter($this->calls, fn ($c) => $c[0] === $fn));
    }

    private function call(string $fn): array
    {
        return $this->calls($fn)[0];
    }
}
