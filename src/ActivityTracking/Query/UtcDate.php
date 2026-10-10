<?php

declare(strict_types=1);

namespace InterviewTask\ActivityTracking\Query;

use DateTimeImmutable;

/**
 * Canonical real UTC calendar date: YYYY-MM-DD, never a locale-dependent date string.
 *
 * @package InterviewTask\ActivityTracking\Query
 */
final readonly class UtcDate
{
    /**
     * Only the validating factory can construct a UTC calendar date.
     *
     * @param string $value Already validated YYYY-MM-DD.
     */
    private function __construct(private string $value) {}

    /**
     * Parse a calendar date without implicit normalization or time-zone conversion.
     *
     * @param string $value Untrusted date text.
     *
     * @return self|null Date when valid, otherwise null.
     */
    public static function tryFrom(string $value) : self|null
    {
        if (preg_match(pattern: '/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', subject: $value) !== 1) {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat(format: '!Y-m-d', datetime: $value);

        return $parsed !== false && $parsed->format(format: 'Y-m-d') === $value
            ? new self(value: $value)
            : null;
    }

    /**
     * Return the exact UTC date suitable for an API, SQLite filter or report.
     *
     * @return string YYYY-MM-DD.
     */
    public function value() : string
    {
        return $this->value;
    }
}
