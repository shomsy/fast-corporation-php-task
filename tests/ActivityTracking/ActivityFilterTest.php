<?php

declare(strict_types=1);

namespace InterviewTask\Tests\ActivityTracking;

use InterviewTask\ActivityTracking\ActivityAction;
use InterviewTask\ActivityTracking\ActivityFilters;
use InterviewTask\ActivityTracking\UtcDate;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Protect owner-level calendar and statistics filter rules independently of SQLite.
 *
 * @package InterviewTask\Tests\ActivityTracking
 */
final class ActivityFilterTest extends TestCase
{
    /**
     * Validate leap days, exact year-month-day syntax, and non-normalization.
     *
     * @return void No return value.
     */
    public function testRealUtcCalendarDatesHaveCanonicalRoundTrip() : void
    {
        self::assertSame('2024-02-29', UtcDate::tryFrom(value: '2024-02-29')?->value());
        foreach (['2023-02-29', '2026-2-09', '2026-13-01', '2026-10-10T00:00:00Z', '2026-01-00', ' 2026-10-10', '2026-10-10 '] as $invalid) {
            self::assertNull(UtcDate::tryFrom(value: $invalid));
        }
    }

    /**
     * Normalize only the intended filters, without changing identity or action semantics.
     *
     * @return void No return value.
     */
    public function testValidFiltersAreTypedAndTrimmed() : void
    {
        $filters = new ActivityFilters(date: '2024-02-29', user: '  Alice  ', action: 'view-page');
        self::assertSame('2024-02-29', $filters->date?->value());
        self::assertSame('Alice', $filters->username);
        self::assertSame(ActivityAction::ViewPage, $filters->action);

        $empty = new ActivityFilters(date: '', user: '   ', action: '');
        self::assertNull($empty->date);
        self::assertNull($empty->username);
        self::assertNull($empty->action);

        $historical = new ActivityFilters(date: null, user: '  Željko 東京  ', action: null);
        self::assertSame('Željko 東京', $historical->username);
    }

    /**
     * Malformed actor filters must fail validation before cursor serialization or SQL.
     *
     * @return void No return value.
     */
    public function testMalformedUtf8UserFailsBeforeCursorSerialization() : void
    {
        foreach (["\xFF", "alice\xC3\x28"] as $user) {
            try {
                new ActivityFilters(date: null, user: $user, action: null);
                self::fail('Malformed UTF-8 must not become a valid user filter.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame('Invalid user filter. Expected UTF-8 text.', $exception->getMessage());
            }
        }
    }

    /**
     * Unrecognized action names must fail before reaching SQLite.
     *
     * @return void No return value.
     */
    public function testUnknownActionFailsBeforeSql() : void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown activity action.');
        new ActivityFilters(date: null, user: null, action: 'not-an-action');
    }

    /**
     * Invalid calendar dates must preserve the public validation message.
     *
     * @return void No return value.
     */
    public function testInvalidDateFailsBeforeSql() : void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid date. Expected YYYY-MM-DD.');
        new ActivityFilters(date: '2026-02-31', user: null, action: null);
    }
}
