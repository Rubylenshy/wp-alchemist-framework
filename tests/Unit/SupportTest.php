<?php

declare(strict_types=1);

namespace WpAlchemist\Framework\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use WpAlchemist\Framework\Support\Arr;
use WpAlchemist\Framework\Support\Str;
use WpAlchemist\Framework\Tests\TestCase;

final class SupportTest extends TestCase
{
    /** @return iterable<array{string, string, string, string, string}> */
    public static function cases(): iterable
    {
        yield ['my plugin-name', 'MyPluginName', 'myPluginName', 'my_plugin_name', 'my-plugin-name'];
        yield ['MyPluginName', 'MyPluginName', 'myPluginName', 'my_plugin_name', 'my-plugin-name'];
        yield ['HTMLParser v2', 'HtmlParserV2', 'htmlParserV2', 'html_parser_v2', 'html-parser-v2'];
        yield ['MY_CONSTANT', 'MyConstant', 'myConstant', 'my_constant', 'my-constant'];
    }

    #[DataProvider('cases')]
    public function testCaseConversion(string $in, string $studly, string $camel, string $snake, string $kebab): void
    {
        $this->assertSame($studly, Str::studly($in));
        $this->assertSame($camel, Str::camel($in));
        $this->assertSame($snake, Str::snake($in));
        $this->assertSame($kebab, Str::kebab($in));
    }

    public function testStringHelpers(): void
    {
        $this->assertSame('MY_PLUGIN', Str::constant('my-plugin'));
        $this->assertSame('Héllo…', Str::limit('Héllo wörld', 5));
        $this->assertSame('short', Str::limit('short', 10));
        $this->assertSame('user', Str::before('user@example.com', '@'));
        $this->assertSame('example.com', Str::after('user@example.com', '@'));
        $this->assertSame('none', Str::after('none', '@'));
    }

    public function testArrayHelpers(): void
    {
        $data = ['user' => ['address' => ['city' => 'Lagos']], 'a.b' => 'literal'];

        $this->assertSame('Lagos', Arr::get($data, 'user.address.city'));
        $this->assertSame('literal', Arr::get($data, 'a.b'));
        $this->assertSame('n/a', Arr::get($data, 'user.phone', 'n/a'));
        $this->assertTrue(Arr::has($data, 'user.address'));
        $this->assertFalse(Arr::has($data, 'user.zip'));
        $this->assertSame(['x' => ['y' => 1]], Arr::set([], 'x.y', 1));
        $this->assertSame(['a' => 1], Arr::only(['a' => 1, 'b' => 2], ['a']));
        $this->assertSame(['b' => 2], Arr::except(['a' => 1, 'b' => 2], ['a']));
        $this->assertSame([1, 2, null], Arr::pluck([['id' => 1], (object) ['id' => 2], []], 'id'));
        $this->assertSame([], Arr::wrap(null));
        $this->assertSame(['x'], Arr::wrap('x'));
    }
}
