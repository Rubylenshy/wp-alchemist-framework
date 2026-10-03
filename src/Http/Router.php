<?php

declare(strict_types=1);

namespace WpAlchemist\Framework\Http;

use Closure;
use InvalidArgumentException;
use WP_Error;
use WP_REST_Request;

/**
 * REST route registry with a mandatory permission on every route.
 *
 * The permission argument is either:
 *  - a capability string, e.g. 'manage_options' (checked with current_user_can), or
 *  - a Closure receiving the WP_REST_Request and returning bool|WP_Error, or
 *  - Router::PUBLIC, an explicit opt-in for unauthenticated endpoints.
 *
 * With cookie authentication WordPress only treats a request as logged in when
 * it carries a valid X-WP-Nonce header, so a capability check also protects
 * against CSRF.
 */
final class Router
{
    public const PUBLIC = '__alchemist_public__';

    /** @var list<array{methods: list<string>, path: string, handler: callable, permission: string|Closure, args: array<string, mixed>}> */
    private array $routes = [];

    private bool $registered = false;

    public function __construct(private readonly string $namespace)
    {
    }

    /** @param array<string, mixed> $args */
    public function get(string $path, callable $handler, string|Closure $permission, array $args = []): self
    {
        return $this->add(['GET'], $path, $handler, $permission, $args);
    }

    /** @param array<string, mixed> $args */
    public function post(string $path, callable $handler, string|Closure $permission, array $args = []): self
    {
        return $this->add(['POST'], $path, $handler, $permission, $args);
    }

    /** @param array<string, mixed> $args */
    public function put(string $path, callable $handler, string|Closure $permission, array $args = []): self
    {
        return $this->add(['PUT', 'PATCH'], $path, $handler, $permission, $args);
    }

    /** @param array<string, mixed> $args */
    public function delete(string $path, callable $handler, string|Closure $permission, array $args = []): self
    {
        return $this->add(['DELETE'], $path, $handler, $permission, $args);
    }

    /**
     * @param list<string>         $methods
     * @param array<string, mixed> $args
     */
    public function add(array $methods, string $path, callable $handler, string|Closure $permission, array $args = []): self
    {
        if ($permission === '') {
            throw new InvalidArgumentException(
                "Route {$path} needs a capability, a Closure, or Router::PUBLIC as its permission."
            );
        }

        $this->routes[] = [
            'methods' => $methods,
            'path' => $path,
            'handler' => $handler,
            'permission' => $permission,
            'args' => $args,
        ];

        return $this;
    }

    /** Register everything with WordPress. Call once, on rest_api_init. */
    public function register(): void
    {
        if ($this->registered) {
            return;
        }

        foreach ($this->routes as $route) {
            register_rest_route($this->namespace, self::pattern($route['path']), [
                'methods' => $route['methods'],
                'callback' => fn (WP_REST_Request $request) => $this->dispatch($route['handler'], $request),
                'permission_callback' => self::permissionCallback($route['permission']),
                'args' => $route['args'],
            ]);
        }

        $this->registered = true;
    }

    /**
     * Convert "/items/{id}" into "/items/(?P<id>[^/]+)".
     */
    public static function pattern(string $path): string
    {
        $path = '/' . trim($path, '/');

        return (string) preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $path);
    }

    public static function permissionCallback(string|Closure $permission): callable
    {
        if ($permission === self::PUBLIC) {
            return '__return_true';
        }

        if ($permission instanceof Closure) {
            return $permission;
        }

        return static fn (): bool => current_user_can($permission);
    }

    /**
     * Run a handler and translate HttpException into WP_Error.
     */
    public function dispatch(callable $handler, WP_REST_Request $request): mixed
    {
        try {
            return rest_ensure_response($handler($request));
        } catch (HttpException $e) {
            return new WP_Error($e->errorCode, $e->getMessage(), ['status' => $e->status] + $e->data);
        }
    }

    public function url(string $path = ''): string
    {
        return rest_url($this->namespace . '/' . ltrim($path, '/'));
    }

    public function namespace(): string
    {
        return $this->namespace;
    }

    /** @return list<array{methods: list<string>, path: string, handler: callable, permission: string|Closure, args: array<string, mixed>}> */
    public function routes(): array
    {
        return $this->routes;
    }
}
