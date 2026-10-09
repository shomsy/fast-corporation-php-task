<?php

declare(strict_types=1);

namespace InterviewTask\ActivityTracking;

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

        $filters          = new ActivityFilters(date: $date, user: $user, action: $action);
        $fingerprintInput = json_encode(value: [$date ?? '', $user === null ? '' : trim(string: $user), $action ?? ''], flags: JSON_THROW_ON_ERROR);
        if (! is_string(value: $fingerprintInput)) {
            throw new RuntimeException(message: 'Unable to encode activity cursor filters.');
        }
        $fingerprint = hash(algo: 'sha256', data: $fingerprintInput);
        $position    = $cursor === null ? null : $this->decodeCursor(cursor: $cursor, key: $key, fingerprint: $fingerprint);
        $direction   = $position['direction'] ?? 'before';

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
            'nextCursor'     => $hasNext ? $this->encodeCursor(time: $last['occurred_at'], id: $last['id'], direction: 'before', fingerprint: $fingerprint, key: $key) : null,
            'previousCursor' => $hasPrevious ? $this->encodeCursor(time: $first['occurred_at'], id: $first['id'], direction: 'after', fingerprint: $fingerprint, key: $key) : null,
        ];
    }

    /**
     * Encode an opaque, MAC-protected navigation position.
     *
     * @param string $time        Exact stored UTC timestamp.
     * @param int    $id          Event ID.
     * @param string $direction   before or after.
     * @param string $fingerprint Bound user/date/action filters.
     * @param string $key         Server-side session secret.
     *
     * @return string URL-safe cursor.
     * @throws RuntimeException If cursor JSON unexpectedly is not a string.
     * @throws JsonException When JSON serialization fails.
     */
    private function encodeCursor(string $time, int $id, string $direction, string $fingerprint, string $key) : string
    {
        $json = json_encode(value: ['v' => 1, 't' => $time, 'id' => $id, 'd' => $direction, 'f' => $fingerprint], flags: JSON_THROW_ON_ERROR);
        if (! is_string(value: $json)) {
            throw new RuntimeException(message: 'Unable to encode activity cursor.');
        }
        $body = rtrim(string: strtr(base64_encode(string: $json), '+/', '-_'), characters: '=');
        $mac  = hash_hmac(algo: 'sha256', data: $body, key: $key);

        return $body . '.' . $mac;
    }

    /**
     * Decode only well-formed cursors authenticated by the current PHP session.
     *
     * @param string $cursor      Untrusted URL parameter.
     * @param string $key         Server-side secret.
     * @param string $fingerprint Filter-bound SHA-256.
     *
     * @return array{time: string, id: int, direction: string}
     * @throws InvalidArgumentException When token encoding, signature or decoded payload is invalid.
     */
    private function decodeCursor(string $cursor, string $key, string $fingerprint) : array
    {
        if (strlen(string: $cursor) > 768 || preg_match(pattern: '/\A[A-Za-z0-9_-]+\.[a-f0-9]{64}\z/', subject: $cursor) !== 1) {
            throw new InvalidArgumentException(message: 'Invalid activity cursor.');
        }
        [$body, $mac] = explode(separator: '.', string: $cursor, limit: 2);
        if (! hash_equals(known_string: hash_hmac(algo: 'sha256', data: $body, key: $key), user_string: $mac)) {
            throw new InvalidArgumentException(message: 'Invalid activity cursor.');
        }
        $decoded = base64_decode(string: strtr($body, '-_', '+/'), strict: true);
        if ($decoded === false) {
            throw new InvalidArgumentException(message: 'Invalid activity cursor.');
        }
        try {
            $value = json_decode(json: $decoded, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException(message: 'Invalid activity cursor.', previous: $exception);
        }
        if (! is_array(value: $value) || count(value: $value) !== 5
            || ($value['v'] ?? null) !== 1 || ($value['f'] ?? null) !== $fingerprint
            || ! is_string(value: $value['t'] ?? null)
            || ! is_int(value: $value['id'] ?? null) || $value['id'] < 1
            || ! in_array(needle: $value['d'] ?? null, haystack: ['before', 'after'], strict: true)
            || preg_match(pattern: '/\\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}\\+00:00\\z/', subject: $value['t']) !== 1) {
            throw new InvalidArgumentException(message: 'Invalid activity cursor.');
        }

        return ['time' => $value['t'], 'id' => $value['id'], 'direction' => $value['d']];
    }

}
