<?php

declare(strict_types=1);

namespace WpAlchemist\Framework;

use ReflectionClass;
use WpAlchemist\Framework\Http\Router;

/**
 * A self-contained feature: its hooks, REST routes, assets and cleanup live together.
 *
 * Keep register() cheap. It runs on every request, so it should only attach
 * hooks; do real work inside the callbacks.
 */
abstract class Module
{
    public function __construct(protected readonly Plugin $plugin)
    {
    }

    /** Attach WordPress hooks. */
    abstract public function register(): void;

    /** Declare REST routes. Called on rest_api_init. */
    public function routes(Router $router): void
    {
    }

    /** Runs on plugin activation, after migrations. */
    public function activate(): void
    {
    }

    /** Runs on plugin deactivation. Unschedule cron events here. */
    public function deactivate(): void
    {
    }

    /** Runs from uninstall.php, once per site. Delete options and other data here. */
    public function uninstall(): void
    {
    }

    protected function config(): Config
    {
        return $this->plugin->config();
    }

    /**
     * Path of a file in this module's resources/ folder, relative to the plugin root.
     * This is the key Vite uses in its manifest.
     */
    protected function resource(string $file): string
    {
        $moduleDir = dirname((string) (new ReflectionClass($this))->getFileName());
        $pluginDir = dirname($this->config()->file);
        $relative = substr($moduleDir, strlen($pluginDir) + 1);

        return str_replace('\\', '/', $relative) . '/resources/' . ltrim($file, '/');
    }
}
