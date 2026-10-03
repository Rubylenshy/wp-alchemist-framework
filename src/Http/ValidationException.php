<?php

declare(strict_types=1);

namespace WpAlchemist\Framework\Http;

/**
 * 422 response carrying per-field error messages under data.errors.
 */
final class ValidationException extends HttpException
{
    /** @param array<string, string> $errors Field name => message. */
    public function __construct(public readonly array $errors, string $message = 'The given data was invalid.')
    {
        parent::__construct($message, 422, 'alchemist_invalid', ['errors' => $errors]);
    }
}
