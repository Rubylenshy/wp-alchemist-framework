<?php

declare(strict_types=1);

namespace WpAlchemist\Framework\Tests\Unit;

use Brain\Monkey\Functions;
use InvalidArgumentException;
use WpAlchemist\Framework\Http\ValidationException;
use WpAlchemist\Framework\Settings\Settings;
use WpAlchemist\Framework\Tests\TestCase;

final class SettingsTest extends TestCase
{
    private Settings $settings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeOptions();
        Functions\when('sanitize_text_field')->alias(fn (string $v): string => trim(strip_tags($v)));

        $this->settings = new Settings('demo_settings', [
            'enabled' => ['type' => 'boolean', 'default' => true],
            'greeting' => ['type' => 'string', 'default' => 'Hi', 'maxLength' => 10],
            'per_page' => ['type' => 'integer', 'default' => 10, 'minimum' => 1, 'maximum' => 100],
            'mode' => ['type' => 'string', 'default' => 'auto', 'enum' => ['auto', 'manual']],
            'tags' => ['type' => 'array', 'default' => []],
        ]);
    }

    public function testReturnsDefaultsWhenNothingIsStored(): void
    {
        $this->assertSame(
            ['enabled' => true, 'greeting' => 'Hi', 'per_page' => 10, 'mode' => 'auto', 'tags' => []],
            $this->settings->all()
        );
    }

    public function testDropsStoredKeysThatAreNotInTheSchema(): void
    {
        $this->options['demo_settings'] = ['per_page' => 25, 'legacy_key' => 'x'];

        $this->assertSame(25, $this->settings->get('per_page'));
        $this->assertArrayNotHasKey('legacy_key', $this->settings->all());
    }

    public function testCastsAndSavesValidValues(): void
    {
        $saved = $this->settings->update([
            'enabled' => 'false',
            'per_page' => '50',
            'greeting' => ' <b>Yo</b> ',
            'tags' => ['a', 'b', ['nested']],
        ]);

        $this->assertFalse($saved['enabled']);
        $this->assertSame(50, $saved['per_page']);
        $this->assertSame('Yo', $saved['greeting']);
        $this->assertSame(['a', 'b'], $saved['tags']);
        $this->assertSame($saved, $this->options['demo_settings']);
    }

    public function testRejectsUnknownAndInvalidValuesWithoutSaving(): void
    {
        try {
            $this->settings->update([
                'per_page' => 500,
                'mode' => 'turbo',
                'greeting' => 'far too long for this',
                'enabled' => 'maybe',
                'admin_email' => 'x@example.com',
            ]);
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertSame(
                ['per_page', 'mode', 'greeting', 'enabled', 'admin_email'],
                array_keys($e->errors)
            );
            $this->assertSame(422, $e->status);
        }

        $this->assertArrayNotHasKey('demo_settings', $this->options);
    }

    public function testRejectsFloatsForIntegers(): void
    {
        $this->expectException(ValidationException::class);

        $this->settings->update(['per_page' => '1.5']);
    }

    public function testUnknownKeyOnGetThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->settings->get('nope');
    }

    public function testSchemaNeedsTypeAndDefault(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Settings('x', ['broken' => ['type' => 'object', 'default' => null]]);
    }

    public function testBuildsRestArgs(): void
    {
        $args = $this->settings->restArgs();

        $this->assertSame(['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'required' => false], $args['per_page']);
        $this->assertSame(['auto', 'manual'], $args['mode']['enum']);
        $this->assertSame(['type' => 'string'], $args['tags']['items']);
    }
}
