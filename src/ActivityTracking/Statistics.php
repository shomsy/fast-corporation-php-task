<?php

declare(strict_types=1);

namespace InterviewTask\ActivityTracking;

use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Stable, bounded and newest-first activity history.
 *
 * @phpstan-type ActivityEventRow array{id: int, actor_id: int, actor_label: string, action: string, target: ?string, occurred_at: string}
 * @phpstan-type ActivityPageResult array{events: list<ActivityEventRow>, hasNext: bool}
 * @api
 * @package InterviewTask\ActivityTracking
 * @see \InterviewTask\ActivityTracking\DailyReports
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
     * @param ?string $date Exact UTC calendar date (YYYY-MM-DD); null means all dates.
     * @param ?string $user Case-insensitive exact actor label, not a substring.
     * @param ?string $action Canonical action value; invalid values are rejected.
     * @param int $page 1-based page number.
     * @return list<ActivityEventRow>
     * @throws \InvalidArgumentException When a submitted value or filter violates the documented precondition.
     */
    public function events(?string $date = null, ?string $user = null, ?string $action = null, int $page = 1): array
    {
        return $this->page(date: $date, user: $user, action: $action, page: $page)['events'];
    }

    /**
     * Return a 1-based page sorted by UTC timestamp then event ID descending. One extra row establishes hasNext without an approximate count.
     *
     * @param ?string $date Exact UTC date (YYYY-MM-DD), or null for all dates.
     * @param ?string $user Case-insensitive exact actor label, or null.
     * @param ?string $action Canonical ActivityAction value, or null.
     * @param int $page 1-based page number.
     * @return ActivityPageResult
     * @throws \InvalidArgumentException When a submitted value or filter violates the documented precondition.
     * @throws \PDOException When the underlying SQLite operation fails.
     * @throws \RuntimeException When the query cannot be prepared.
     */
    public function page(?string $date = null, ?string $user = null, ?string $action = null, int $page = 1): array
    {
        if ($page < 1 || $page > intdiv(num1: PHP_INT_MAX, num2: self::PAGE_SIZE) - 1) {
            throw new InvalidArgumentException(message: 'Invalid page.');
        }

        $where      = [];
        $parameters = [];

        if ($date !== null && $date !== '') {
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

        $sql = 'SELECT id, actor_id, actor_label, action, target, occurred_at FROM activity_events';

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
}
