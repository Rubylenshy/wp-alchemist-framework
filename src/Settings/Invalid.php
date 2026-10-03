<?php

declare(strict_types=1);

namespace WpAlchemist\Framework\Settings;

/**
 * @internal Marker for a value that failed validation.
 */
final class Invalid
{
    public function __construct(public readonly string $message)
    {
    }
}
