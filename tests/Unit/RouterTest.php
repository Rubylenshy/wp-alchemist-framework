<?php

declare(strict_types=1);

namespace WpAlchemist\Framework\Tests\Unit;

use Brain\Monkey\Functions;
use InvalidArgumentException;
use WP_Error;
use WP_REST_Request;
use WpAlchemist\Framework\Http\HttpException;
use WpAlchemist\Framework\Http\Router;
use WpAlchemist\Framework\Http\ValidationException;
use WpAlchemist\Framework\Tests\TestCase;

final class RouterTest extends TestCase
{
    public function testConvertsPlaceholdersToNamedGroups(): void
    {
        $this->assertSame('/items/(?P<id>[^/]+)', Router::pattern('items/{id}'));
        $this->assertSame('/a/(?P<x>[^/]+)/b/(?P<y_2>[^/]+)', Router::pattern('/a/{x}/b/{y_2}/'));
        $this->assertSame('/settings', Router::pattern('settings'));
    }

    public function testRejectsEmptyPermission(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Router('demo/v1'))->get('/x', fn () => [], '');
    }

    public function testCapabilityPermissionChecksCurrentUser(): void
    {
        Functions\expect('current_user_can')->once()->with('manage_options')->andReturn(false);

        $callback = Router::permissionCallback('manage_options');

        $this->assertFalse($callback());
    }

    public function testPublicPermissionIsExplicit(): void
    {
        $this->assertSame('__return_true', Router::permissionCallback(Router::PUBLIC));
    }

    public function testClosurePermissionIsUsedAsIs(): void
    {
        $closure = fn (): bool => true;

        $this->assertSame($closure, Router::permissionCallback($closure));
    }

    public function testRegistersEveryRouteWithAPermissionCallback(): void
    {
        $registered = [];
        Functions\when('register_rest_route')->alias(function (string $ns, string $route, array $args) use (&$registered): bool {
            $registered[] = [$ns, $route, $args];
            return true;
        });

        $router = new Router('demo/v1');
        $router->get('/settings', fn () => [], 'manage_options')
            ->post('/items/{id}', fn () => [], Router::PUBLIC, ['id' => ['type' => 'integer']]);
        $router->register();
        $router->register(); // second call is a no-op

        $this->assertCount(2, $registered);
        $this->assertSame(['demo/v1', '/settings'], array_slice($registered[0], 0, 2));
        $this->assertSame(['GET'], $registered[0][2]['methods']);
        $this->assertIsCallable($registered[0][2]['permission_callback']);
        $this->assertSame('/items/(?P<id>[^/]+)', $registered[1][1]);
        $this->assertSame('__return_true', $registered[1][2]['permission_callback']);
        $this->assertSame(['id' => ['type' => 'integer']], $registered[1][2]['args']);
    }

    public function testDispatchWrapsResultInResponse(): void
    {
        Functions\when('rest_ensure_response')->alias(fn ($data) => ['wrapped' => $data]);

        $result = (new Router('demo/v1'))->dispatch(fn (WP_REST_Request $r) => ['id' => $r->get_param('id')], new WP_REST_Request(['id' => 7]));

        $this->assertSame(['wrapped' => ['id' => 7]], $result);
    }

    public function testDispatchTurnsHttpExceptionIntoWpError(): void
    {
        $result = (new Router('demo/v1'))->dispatch(
            fn () => throw HttpException::notFound('Nope.'),
            new WP_REST_Request()
        );

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('alchemist_not_found', $result->get_error_code());
        $this->assertSame(['status' => 404], $result->get_error_data());
    }

    public function testValidationErrorsAreExposedToTheClient(): void
    {
        $result = (new Router('demo/v1'))->dispatch(
            fn () => throw new ValidationException(['title' => 'Required.']),
            new WP_REST_Request()
        );

        $this->assertSame(['status' => 422, 'errors' => ['title' => 'Required.']], $result->get_error_data());
    }
}
