<?php

declare(strict_types=1);

namespace WpAlchemist\Framework;

use LogicException;
use WpAlchemist\Framework\Assets\Vite;
use WpAlchemist\Framework\Database\Migrator;
use WpAlchemist\Framework\Database\Schema;
use WpAlchemist\Framework\Http\Router;

/**
 * Plugin kernel. Each plugin extends this once and lists its modules.
 *
 * Lifecycle:
 *  - boot()        main plugin file: registers activation/deactivation hooks and plugins_loaded.
 *  - load()        plugins_loaded: instantiates modules and wires translations, upgrades and REST.
 *  - activate()    runs pending migrations (per site when network-activated).
 *  - deactivate()  lets modules unschedule cron events.
 *  - uninstall()   from uninstall.php: rolls back migrations and lets modules delete their data.
 */
abstract class Plugin
{
    /** @var array<class-string<Plugin>, Plugin> */
    private static array $instances = [];

    /** @var array<class-string<Module>, Module> */
    private array $modules = [];

    private ?Router $router = null;
    private ?Vite $vite = null;
    private ?Migrator $migrator = null;

    final public function __construct(private readonly Config $config)
    {
    }

    /** @return list<class-string<Module>> */
    abstract protected function modules(): array;

    public static function boot(string $file): static
    {
        if (isset(self::$instances[static::class])) {
            throw new LogicException(static::class . ' has already been booted.');
        }

        $plugin = new static(Config::fromFile($file));
        self::$instances[static::class] = $plugin;

        register_activation_hook($file, [$plugin, 'activate']);
        register_deactivation_hook($file, [$plugin, 'deactivate']);
        add_action('plugins_loaded', [$plugin, 'load']);

        return $plugin;
    }

    public static function instance(): static
    {
        $plugin = self::$instances[static::class] ?? null;

        if (!$plugin instanceof static) {
            throw new LogicException(static::class . ' has not been booted yet.');
        }

        return $plugin;
    }

    /**
     * Called from uninstall.php. WordPress does not load the main plugin file
     * during uninstall, so the kernel is rebuilt here from the file path.
     */
    public static function uninstall(string $file): void
    {
        $plugin = new static(Config::fromFile($file));

        $plugin->forEachSite(static function () use ($plugin): void {
            foreach ($plugin->makeModules() as $module) {
                $module->uninstall();
            }

            $plugin->migrator()->reset();
            delete_option($plugin->config->option('version'));
        }, true);
    }

    public function load(): void
    {
        $this->modules = $this->makeModules();

        add_action('init', [$this, 'loadTextDomain']);
        add_action('admin_init', [$this, 'maybeUpgrade']);
        add_action('rest_api_init', [$this, 'registerRoutes']);

        foreach ($this->modules as $module) {
            $module->register();
        }

        do_action($this->config->hook('loaded'), $this);
    }

    public function activate(bool $networkWide = false): void
    {
        $this->forEachSite(function (): void {
            $this->upgrade();

            foreach ($this->modules ?: $this->makeModules() as $module) {
                $module->activate();
            }
        }, $networkWide);
    }

    public function deactivate(bool $networkWide = false): void
    {
        $this->forEachSite(function (): void {
            foreach ($this->modules ?: $this->makeModules() as $module) {
                $module->deactivate();
            }
        }, $networkWide);
    }

    /**
     * Runs migrations when the plugin was updated without being re-activated
     * (WordPress does not fire the activation hook on updates).
     */
    public function maybeUpgrade(): void
    {
        if (get_option($this->config->option('version')) !== $this->config->version) {
            $this->upgrade();
        }
    }

    public function loadTextDomain(): void
    {
        load_plugin_textdomain(
            $this->config->textDomain,
            false,
            dirname($this->config->basename()) . '/languages'
        );
    }

    public function registerRoutes(): void
    {
        foreach ($this->modules as $module) {
            $module->routes($this->router());
        }

        $this->router()->register();
    }

    public function config(): Config
    {
        return $this->config;
    }

    public function router(): Router
    {
        return $this->router ??= new Router($this->config->restNamespace);
    }

    public function vite(): Vite
    {
        return $this->vite ??= new Vite($this->config);
    }

    public function migrator(): Migrator
    {
        global $wpdb;

        return $this->migrator ??= new Migrator(
            $this->migrationsPath(),
            $this->config->option('migrations'),
            new Schema($wpdb, $this->config->prefix)
        );
    }

    /**
     * @template T of Module
     * @param class-string<T> $class
     * @return T
     */
    public function module(string $class): Module
    {
        $module = $this->modules[$class] ?? null;

        if (!$module instanceof $class) {
            throw new LogicException("Module {$class} is not registered.");
        }

        return $module;
    }

    protected function migrationsPath(): string
    {
        return $this->config->path('database/migrations');
    }

    private function upgrade(): void
    {
        $this->migrator()->migrate();
        update_option($this->config->option('version'), $this->config->version);
    }

    /** @return array<class-string<Module>, Module> */
    private function makeModules(): array
    {
        $modules = [];

        foreach ($this->modules() as $class) {
            $modules[$class] = new $class($this);
        }

        return $modules;
    }

    private function forEachSite(callable $callback, bool $allSites): void
    {
        if (!$allSites || !is_multisite()) {
            $callback();
            return;
        }

        foreach (get_sites(['fields' => 'ids', 'number' => 0]) as $siteId) {
            switch_to_blog((int) $siteId);
            try {
                $callback();
            } finally {
                restore_current_blog();
            }
        }
    }
}
