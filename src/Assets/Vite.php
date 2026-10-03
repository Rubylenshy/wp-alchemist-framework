<?php

declare(strict_types=1);

namespace WpAlchemist\Framework\Assets;

use RuntimeException;
use WpAlchemist\Framework\Config;

/**
 * Enqueues Vite entry points.
 *
 * Production: reads build/.vite/manifest.json and enqueues the hashed JS file
 * plus every CSS file it (or its imported chunks) needs.
 * Development: when build/hot exists (written by `npm run dev`), scripts load
 * from the Vite dev server so hot module replacement works inside wp-admin.
 */
final class Vite
{
    /** @var array<string, array<string, mixed>>|null */
    private ?array $manifest = null;

    /** @var array<string, true> */
    private array $moduleHandles = [];

    private bool $filterAdded = false;

    public function __construct(private readonly Config $config, private readonly string $buildDir = 'build')
    {
    }

    /**
     * @param string               $entry      Source path relative to the plugin root, e.g. "src/Modules/Settings/resources/main.js".
     * @param string|null          $objectName Global JS variable that receives $data (assigned before the entry runs).
     * @param array<string, mixed> $data
     * @return string The script handle.
     */
    public function enqueue(string $entry, ?string $objectName = null, array $data = []): string
    {
        $handle = $this->handle($entry);

        if ($this->isHot()) {
            $server = $this->devServerUrl();
            $this->enqueueModule($this->config->slug . '-vite-client', $server . '/@vite/client');
            $this->enqueueModule($handle, $server . '/' . $entry);
        } else {
            $chunk = $this->chunk($entry);
            $this->enqueueModule($handle, $this->buildUrl((string) $chunk['file']));

            foreach ($this->collectCss($entry) as $i => $css) {
                wp_enqueue_style($handle . '-' . $i, $this->buildUrl($css), [], null);
            }
        }

        if ($objectName !== null) {
            wp_add_inline_script(
                $handle,
                sprintf('window.%s = %s;', $objectName, wp_json_encode($data)),
                'before'
            );
        }

        return $handle;
    }

    public function isHot(): bool
    {
        return is_file($this->hotFile());
    }

    public function handle(string $entry): string
    {
        $name = (string) preg_replace('/\.[a-z]+$/i', '', $entry);
        $name = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $name));

        return $this->config->slug . '-' . trim($name, '-');
    }

    /**
     * Adds type="module" to the script tags this class enqueued.
     * Hooked to script_loader_tag.
     */
    public function addModuleType(string $tag, string $handle): string
    {
        if (!isset($this->moduleHandles[$handle])) {
            return $tag;
        }

        // Only touch the external tag; inline "before" scripts must stay classic.
        return (string) preg_replace_callback(
            '#<script\b[^>]*\bsrc=[^>]*>#i',
            static function (array $m): string {
                $open = (string) preg_replace('#\stype=(["\'])[^"\']*\1#i', '', $m[0]);
                return (string) preg_replace('#^<script\b#i', '<script type="module"', $open);
            },
            $tag
        );
    }

    /** @return array<string, mixed> */
    private function chunk(string $entry): array
    {
        $manifest = $this->manifest();

        if (!isset($manifest[$entry]['file'])) {
            throw new RuntimeException(sprintf(
                'Vite entry "%s" is not in the manifest. Run `npm run build` (or `npm run dev`).',
                $entry
            ));
        }

        return $manifest[$entry];
    }

    /**
     * CSS of the entry and of every chunk it statically imports.
     *
     * @param array<string, true> $seen
     * @return list<string>
     */
    private function collectCss(string $key, array &$seen = []): array
    {
        if (isset($seen[$key])) {
            return [];
        }
        $seen[$key] = true;

        $chunk = $this->manifest()[$key] ?? [];
        $css = array_values($chunk['css'] ?? []);

        foreach ($chunk['imports'] ?? [] as $import) {
            array_push($css, ...$this->collectCss($import, $seen));
        }

        return array_values(array_unique($css));
    }

    /** @return array<string, array<string, mixed>> */
    private function manifest(): array
    {
        if ($this->manifest !== null) {
            return $this->manifest;
        }

        $path = $this->config->path($this->buildDir . '/.vite/manifest.json');

        if (!is_file($path)) {
            throw new RuntimeException("Vite manifest not found at {$path}. Run `npm run build`.");
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (!is_array($decoded)) {
            throw new RuntimeException("Vite manifest at {$path} is not valid JSON.");
        }

        return $this->manifest = $decoded;
    }

    private function enqueueModule(string $handle, string $src): void
    {
        wp_enqueue_script($handle, $src, [], null, ['in_footer' => true]);
        $this->moduleHandles[$handle] = true;

        if (!$this->filterAdded) {
            add_filter('script_loader_tag', [$this, 'addModuleType'], 10, 2);
            $this->filterAdded = true;
        }
    }

    private function hotFile(): string
    {
        return $this->config->path($this->buildDir . '/hot');
    }

    private function devServerUrl(): string
    {
        return rtrim(trim((string) file_get_contents($this->hotFile())), '/');
    }

    private function buildUrl(string $file): string
    {
        return $this->config->url($this->buildDir . '/' . $file);
    }
}
