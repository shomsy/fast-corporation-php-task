<?php

declare(strict_types=1);

namespace InterviewTask\ActivityTracking;

use InterviewTask\ActivityTracking\Query\ActivityCursor;
use InterviewTask\ActivityTracking\Query\ActivityFilters;
use InterviewTask\ActivityTracking\Persistence\ReadActivityHistory;
use InvalidArgumentException;
use JsonException;
use PDOException;
use RuntimeException;

/**
 * Stable, bounded and newest-first activity history.
 *
 * @phpstan-type ActivityEventRow array{
 *     id: int,
 *     actor_id: int,
 *     actor_label: string,
 *     action: string,
 *     target: string|null,
 *     occurred_at: string
 * }
 * @phpstan-type ActivityPageResult array{events: list<ActivityEventRow>, hasNext: bool}
 * @phpstan-type CursorPageResult array{
 *     events: list<ActivityEventRow>,
 *     hasNext: bool,
 *     nextCursor: string|null,
 *     previousCursor: string|null
 * }
 * @api
 * @package InterviewTask\ActivityTracking
 * @see     DailyReports
 */
final readonly class Statistics
{
    /**
     * Maximum number of event rows displayed on one statistics page.
     *
     * @var int
     */
    public const int PAGE_SIZE = 50;

    /**
     * Use the owner-private ActivityTracking reader for read-only history queries.
     *
     * @param ReadActivityHistory $reader Owner-private SQLite read model.
     */
    public function __construct(private ReadActivityHistory $reader) {}

    /**
     * Return one 1-based page of event rows, excluding pagination metadata.
     *
     * @param string|null $date   Exact UTC calendar date (YYYY-MM-DD); null means all dates.
     * @param string|null $user   Case-insensitive exact actor label, not a substring.
     * @param string|null $action Canonical action value; invalid values are rejected.
     * @param int         $page   1-based page number.
     *
     * @return list<array{
     *     id: int,
     *     actor_id: int,
     *     actor_label: string,
     *     action: string,
     *     target: string|null,
     *     occurred_at: string
     * }>
     * @throws InvalidArgumentException When a submitted value or filter violates the documented precondition.
     */
    public function events(string|null $date = null, string|null $user = null, string|null $action = null, int $page = 1) : array
    {
        return $this->page(date: $date, user: $user, action: $action, page: $page)['events'];
    }

    /**
     * Return a 1-based page sorted by UTC timestamp then event ID descending. One extra row establishes hasNext
     * without an approximate count.
     *
     * @param string|null $date   Exact UTC date (YYYY-MM-DD), or null for all dates.
     * @param string|null $user   Case-insensitive exact actor label, or null.
     * @param string|null $action Canonical ActivityAction value, or null.
     * @param int         $page   1-based page number.
     *
     * @return array{
     *     events: list<array{
     *         id: int,
     *         actor_id: int,
     *         actor_label: string,
     *         action: string,
     *         target: string|null,
     *         occurred_at: string
     *     }>,
     *     hasNext: bool
     * }
     * @throws InvalidArgumentException When a submitted value or filter violates the documented precondition.
     * @throws PDOException When the underlying SQLite operation fails.
     * @throws RuntimeException When the query cannot be prepared.
     */
    public function page(string|null $date = null, string|null $user = null, string|null $action = null, int $page = 1) : array
    {
        if ($page < 1 || $page > intdiv(num1: PHP_INT_MAX, num2: self::PAGE_SIZE) - 1) {
            throw new InvalidArgumentException(message: 'Invalid page.');
        }

        $filters = new ActivityFilters(date: $date, user: $user, action: $action);

        return $this->reader->page(filters: $filters, page: $page);
    }

    /**
     * Traverse one stable activity page using a signed, filter-bound keyset position.
     *
     * @param string|null $cursor Opaque session-bound cursor, or null for the first page.
     * @param string|null $key    Secret from the authenticated PHP session; never send to browser.
     * @param string|null $date   Optional validated UTC date filter.
     * @param string|null $user   Optional username filter.
     * @param string|null $action Optional action filter.
     *
     * @return CursorPageResult
     * @throws InvalidArgumentException When signing context, filters or a cursor are invalid.
     * @throws RuntimeException When preparing a query or encoding a cursor fails.
     * @throws PDOException When the SQLite statement fails.
     * @throws JsonException When serializing the signed cursor fails.
     */
    public function cursorPage(string|null $cursor = null, string|null $key = null, string|null $date = null, string|null $user = null, string|null $action = null) : array
    {
        if ($key === null || $key === '') {
            throw new InvalidArgumentException(message: 'Missing cursor signing context.');
        }

        $filters   = new ActivityFilters(date: $date, user: $user, action: $action);
        $codec     = new ActivityCursor(key: $key, filters: $filters);
        $position  = $cursor === null ? null : $codec->decode(cursor: $cursor);
        $direction = $position['direction'] ?? 'before';

        $slice   = $this->reader->cursorSlice(filters: $filters, position: $position, direction: $direction);
        $rows    = $slice['rows'];
        $hasMore = $slice['hasMore'];

        if ($rows === []) {
            return ['events' => [], 'hasNext' => false, 'nextCursor' => null, 'previousCursor' => null];
        }

        $last        = $rows[count(value: $rows) - 1];
        $first       = $rows[0];
        $hasNext     = $direction === 'after' ? true : $hasMore;
        $hasPrevious = $direction === 'after' ? $hasMore : $position !== null;

        return [
            'events'         => $rows,
            'hasNext'        => $hasNext,
            'nextCursor'     => $hasNext ? $codec->encode(time: $last['occurred_at'], id: $last['id'], direction: 'before') : null,
            'previousCursor' => $hasPrevious ? $codec->encode(time: $first['occurred_at'], id: $first['id'], direction: 'after') : null,
        ];
    }


}
