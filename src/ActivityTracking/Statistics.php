<?php

declare(strict_types=1);

namespace InterviewTask\ActivityTracking;

use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Stable, bounded and newest-first activity history.
 *
 * @phpstan-type ActivityEventRow array{id: int, actor_id: int, actor_label: string, action: string, target: string|null, occurred_at: string}
 * @phpstan-type ActivityPageResult array{events: list<ActivityEventRow>, hasNext: bool}
 * @phpstan-type CursorPageResult array{events: list<ActivityEventRow>, hasNext: bool, nextCursor: string|null, previousCursor: string|null}
 * @api
 * @package InterviewTask\ActivityTracking
 * @see DailyReports
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
     * Use the ActivityTracking owner database for read-only history queries.
     *
     * @param PDO $pdo SQLite PDO connection with configured driver settings.
     */
    public function __construct(private PDO $pdo) {}

    /**
     * Return one 1-based page of event rows, excluding pagination metadata.
     *
     * @param string|null $date Exact UTC calendar date (YYYY-MM-DD); null means all dates.
     * @param string|null $user Case-insensitive exact actor label, not a substring.
     * @param string|null $action Canonical action value; invalid values are rejected.
     * @param int $page 1-based page number.
     * @return list<array{id: int, actor_id: int, actor_label: string, action: string, target: string|null, occurred_at: string}>
     * @throws InvalidArgumentException When a submitted value or filter violates the documented precondition.
     */
    public function events(string|null $date = null, string|null $user = null, string|null $action = null, int $page = 1): array
    {
        return $this->page(date: $date, user: $user, action: $action, page: $page)['events'];
    }

    /**
     * Return a 1-based page sorted by UTC timestamp then event ID descending. One extra row establishes hasNext without an approximate count.
     *
     * @param string|null $date Exact UTC date (YYYY-MM-DD), or null for all dates.
     * @param string|null $user Case-insensitive exact actor label, or null.
     * @param string|null $action Canonical ActivityAction value, or null.
     * @param int $page 1-based page number.
     * @return ActivityPageResult
     * @throws InvalidArgumentException When a submitted value or filter violates the documented precondition.
     * @throws PDOException When the underlying SQLite operation fails.
     * @throws RuntimeException When the query cannot be prepared.
     */
    public function page(string|null $date = null, string|null $user = null, string|null $action = null, int $page = 1): array
    {
        if ($page < 1 || $page > intdiv(num1: PHP_INT_MAX, num2: self::PAGE_SIZE) - 1) {
            throw new InvalidArgumentException(message: 'Invalid page.');
        }

        $filters = $this->filters(date: $date, user: $user, action: $action);
        $where = $filters['where'];
        $parameters = $filters['parameters'];

        // Date-filtered requests must stay on the existing day expression index even
        // for frequent actors; a competing actor-first index can cause a long scan.
        $table = $date !== null && $date !== '' ? 'activity_events INDEXED BY idx_activity_day' : 'activity_events';
        $sql   = 'SELECT id, actor_id, actor_label, action, target, occurred_at FROM ' . $table;

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(separator: ' AND ', array: $where);
        }

        $sql .= ' ORDER BY occurred_at DESC, id DESC LIMIT :limit OFFSET :offset';

        $statement = $this->pdo->prepare(query: $sql);

        if ($statement === false) {
            throw new RuntimeException(message: 'Unable to prepare activity statistics query.');
        }

        foreach ($parameters as $key => $value) {
            $statement->bindValue(param: ':' . $key, value: $value, type: PDO::PARAM_STR);
        }
        $statement->bindValue(param: ':limit', value: self::PAGE_SIZE + 1, type: PDO::PARAM_INT);
        $statement->bindValue(param: ':offset', value: ($page - 1) * self::PAGE_SIZE, type: PDO::PARAM_INT);
        $statement->execute();

        /** @var list<ActivityEventRow> $rows */
        $rows = $statement->fetchAll(mode: PDO::FETCH_ASSOC);

        return [
            'events'  => array_slice(array: $rows, offset: 0, length: self::PAGE_SIZE),
            'hasNext' => count(value: $rows) > self::PAGE_SIZE,
        ];
    }
    /**
     * Validate shared public statistics filters and create bound SQL predicates.
     *
     * @param string|null $date Exact UTC date.
     * @param string|null $user Historical username.
     * @param string|null $action Canonical event action.
     * @return array{where: list<string>, parameters: array<string, string>}
     */
    private function filters(string|null $date, string|null $user, string|null $action): array
    {
        $where      = [];
        $parameters = [];

        if ($date !== null && $date !== '') {
            if (preg_match(pattern: '/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', subject: $date) !== 1) {
                throw new InvalidArgumentException(message: 'Invalid date. Expected YYYY-MM-DD.');
            }

            $parsed = DateTimeImmutable::createFromFormat(format: '!Y-m-d', datetime: $date);

            if ($parsed === false || $parsed->format(format: 'Y-m-d') !== $date) {
                throw new InvalidArgumentException(message: 'Invalid date. Expected YYYY-MM-DD.');
            }

            $where[]            = 'substr(occurred_at, 1, 10) = :date';
            $parameters['date'] = $date;
        }

        if ($user !== null && trim(string: $user) !== '') {
            $where[]            = 'actor_label = :user COLLATE NOCASE';
            $parameters['user'] = trim(string: $user);
        }

        if ($action !== null && $action !== '') {
            $validated = ActivityAction::tryFrom(value: $action);

            if ($validated === null) {
                throw new InvalidArgumentException(message: 'Unknown activity action.');
            }

            $where[]              = 'action = :action';
            $parameters['action'] = $validated->value;
        }

        return ['where' => $where, 'parameters' => $parameters];
    }


    /**
     * Traverse one stable activity page using a signed, filter-bound keyset position.
     *
     * @param string|null $cursor Opaque session-bound cursor, or null for the first page.
     * @param string $key Secret from the authenticated PHP session; never send to browser.
     * @param string|null $date Optional validated UTC date filter.
     * @param string|null $user Optional username filter.
     * @param string|null $action Optional action filter.
     * @return CursorPageResult
     */
    public function cursorPage(string|null $cursor = null, string $key = '', string|null $date = null, string|null $user = null, string|null $action = null): array
    {
        if ($key === '') {
            throw new InvalidArgumentException(message: 'Missing cursor signing context.');
        }

        $filters = $this->filters(date: $date, user: $user, action: $action);
        $where = $filters['where'];
        $parameters = $filters['parameters'];
        $fingerprint = hash(algo: 'sha256', data: json_encode(value: [$date ?? '', $user === null ? '' : trim(string: $user), $action ?? ''], flags: JSON_THROW_ON_ERROR));
        $position = $cursor === null ? null : $this->decodeCursor(cursor: $cursor, key: $key, fingerprint: $fingerprint);
        $direction = $position['direction'] ?? 'before';

        if ($position !== null) {
            $comparison = $direction === 'after' ? '>' : '<';
            $where[] = '(occurred_at ' . $comparison . ' :cursor_time OR (occurred_at = :cursor_same_time AND id ' . $comparison . ' :cursor_id))';
            $parameters['cursor_time'] = $position['time'];
            $parameters['cursor_same_time'] = $position['time'];
        }

        $table = $date !== null && $date !== '' ? 'activity_events INDEXED BY idx_activity_day' : 'activity_events';
        $sql = 'SELECT id, actor_id, actor_label, action, target, occurred_at FROM ' . $table;
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(separator: ' AND ', array: $where);
        }
        $sort = $direction === 'after' ? 'ASC' : 'DESC';
        $sql .= ' ORDER BY occurred_at ' . $sort . ', id ' . $sort . ' LIMIT :limit';
        $statement = $this->pdo->prepare(query: $sql);
        if ($statement === false) {
            throw new RuntimeException(message: 'Unable to prepare cursor activity history.');
        }
        foreach ($parameters as $name => $value) {
            $statement->bindValue(param: ':' . $name, value: $value, type: PDO::PARAM_STR);
        }
        if ($position !== null) {
            $statement->bindValue(param: ':cursor_id', value: $position['id'], type: PDO::PARAM_INT);
        }
        $statement->bindValue(param: ':limit', value: self::PAGE_SIZE + 1, type: PDO::PARAM_INT);
        $statement->execute();

        /** @var list<ActivityEventRow> $rows */
        $rows = $statement->fetchAll(mode: PDO::FETCH_ASSOC);
        $hasMore = count(value: $rows) > self::PAGE_SIZE;
        $rows = array_slice(array: $rows, offset: 0, length: self::PAGE_SIZE);
        if ($direction === 'after') {
            $rows = array_reverse(array: $rows);
        }

        if ($rows === []) {
            return ['events' => [], 'hasNext' => false, 'nextCursor' => null, 'previousCursor' => null];
        }

        $last = $rows[count(value: $rows) - 1];
        $first = $rows[0];
        $hasNext = $direction === 'after' ? true : $hasMore;
        $hasPrevious = $direction === 'after' ? $hasMore : $position !== null;

        return [
            'events' => $rows,
            'hasNext' => $hasNext,
            'nextCursor' => $hasNext ? $this->encodeCursor(time: $last['occurred_at'], id: $last['id'], direction: 'before', fingerprint: $fingerprint, key: $key) : null,
            'previousCursor' => $hasPrevious ? $this->encodeCursor(time: $first['occurred_at'], id: $first['id'], direction: 'after', fingerprint: $fingerprint, key: $key) : null,
        ];
    }

    /**
     * Encode an opaque, MAC-protected navigation position.
     *
     * @param string $time Exact stored UTC timestamp.
     * @param int $id Event ID.
     * @param string $direction before or after.
     * @param string $fingerprint Bound user/date/action filters.
     * @param string $key Server-side session secret.
     * @return string URL-safe cursor.
     */
    private function encodeCursor(string $time, int $id, string $direction, string $fingerprint, string $key): string
    {
        $json = json_encode(value: ['v' => 1, 't' => $time, 'id' => $id, 'd' => $direction, 'f' => $fingerprint], flags: JSON_THROW_ON_ERROR);
        $body = rtrim(string: strtr(string: base64_encode(string: $json), from: '+/', to: '-_'), characters: '=');
        $mac = hash_hmac(algo: 'sha256', data: $body, key: $key);

        return $body . '.' . $mac;
    }

    /**
     * Decode only well-formed cursors authenticated by the current PHP session.
     *
     * @param string $cursor Untrusted URL parameter.
     * @param string $key Server-side secret.
     * @param string $fingerprint Filter-bound SHA-256.
     * @return array{time: string, id: int, direction: string}
     */
    private function decodeCursor(string $cursor, string $key, string $fingerprint): array
    {
        if (strlen(string: $cursor) > 768 || preg_match(pattern: '/\\A([A-Za-z0-9_-]+)\\.([a-f0-9]{64})\\z/D', subject: $cursor, matches: $parts) !== 1) {
            throw new InvalidArgumentException(message: 'Invalid activity cursor.');
        }
        if (! hash_equals(known_string: hash_hmac(algo: 'sha256', data: $parts[1], key: $key), user_string: $parts[2])) {
            throw new InvalidArgumentException(message: 'Invalid activity cursor.');
        }
        $decoded = base64_decode(string: strtr(string: $parts[1], from: '-_', to: '+/'), strict: true);
        if ($decoded === false) {
            throw new InvalidArgumentException(message: 'Invalid activity cursor.');
        }
        $value = json_decode(json: $decoded, associative: true);
        if (! is_array(value: $value) || count(value: $value) !== 5
            || ($value['v'] ?? null) !== 1 || ($value['f'] ?? null) !== $fingerprint
            || ! is_string(value: $value['t'] ?? null)
            || preg_match(pattern: '/\\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}\\+00:00\\z/D', subject: $value['t']) !== 1
            || ! is_int(value: $value['id'] ?? null) || $value['id'] < 1
            || ! in_array(needle: $value['d'] ?? null, haystack: ['before', 'after'], strict: true)) {
            throw new InvalidArgumentException(message: 'Invalid activity cursor.');
        }

        return ['time' => $value['t'], 'id' => $value['id'], 'direction' => $value['d']];
    }

}
