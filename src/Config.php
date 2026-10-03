<?php

declare(strict_types=1);

namespace WpAlchemist\Framework;

/**
 * Immutable plugin metadata.
 *
 * The version and text domain are read from the plugin header so they are
 * declared exactly once, in the main plugin file.
 */
final class Config
{
    public function __construct(
        public readonly string $file,
        public readonly string $slug,
        public readonly string $version,
        public readonly string $textDomain,
        public readonly string $prefix,
        public readonly string $restNamespace,
    ) {
    }

    /**
     * Build the config from the main plugin file's header.
     *
     * @param array<string, string> $overrides Values that take precedence over the derived ones.
     */
    public static function fromFile(string $file, array $overrides = []): self
    {
        $headers = get_file_data($file, [
            'version' => 'Version',
            'textDomain' => 'Text Domain',
        ]);

        $slug = $overrides['slug'] ?? basename(dirname($file));

        return new self(
            file: $file,
            slug: $slug,
            version: $overrides['version'] ?? ($headers['version'] ?: '0.0.0'),
            textDomain: $overrides['textDomain'] ?? ($headers['textDomain'] ?: $slug),
            prefix: $overrides['prefix'] ?? str_replace('-', '_', $slug),
            restNamespace: $overrides['restNamespace'] ?? $slug . '/v1',
        );
    }

    /** Absolute filesystem path inside the plugin directory. */
    public function path(string $relative = ''): string
    {
        return rtrim(dirname($this->file), '/\\') . '/' . ltrim($relative, '/\\');
    }

    /** Public URL inside the plugin directory. */
    public function url(string $relative = ''): string
    {
        return plugin_dir_url($this->file) . ltrim($relative, '/');
    }

    /** Prefixed option name, e.g. "my_plugin_settings". */
    public function option(string $key): string
    {
        return $this->prefix . '_' . $key;
    }

    /** Prefixed hook name, e.g. "my_plugin/booted". */
    public function hook(string $name): string
    {
        return $this->prefix . '/' . $name;
    }

    public function basename(): string
    {
        return plugin_basename($this->file);
    }
}
