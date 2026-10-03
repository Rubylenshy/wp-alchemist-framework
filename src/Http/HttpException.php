<?php

declare(strict_types=1);

namespace WpAlchemist\Framework\Http;

use RuntimeException;

/**
 * Throw from a route handler to return a WP_Error with the given HTTP status.
 */
class HttpException extends RuntimeException
{
    /** @param array<string, mixed> $data Extra data merged into the WP_Error data. */
    public function __construct(
        string $message,
        public readonly int $status = 400,
        public readonly string $errorCode = 'alchemist_error',
        public readonly array $data = [],
    ) {
        parent::__construct($message, $status);
    }

    public static function notFound(string $message = 'Not found.'): self
    {
        return new self($message, 404, 'alchemist_not_found');
    }

    public static function forbidden(string $message = 'You are not allowed to do that.'): self
    {
        return new self($message, 403, 'alchemist_forbidden');
    }
}
