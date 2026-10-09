<?php

declare(strict_types=1);

namespace InterviewTask\ActivityTracking\Persistence;

use InterviewTask\ActivityTracking\ActivityFilters;
use InterviewTask\ActivityTracking\Statistics;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Owner-private SQLite activity history read model, with bounded offset and keyset queries.
 *
 * @package InterviewTask\ActivityTracking\Persistence
 */
final readonly class ReadActivityHistory
{
    /**
     * @param PDO $pdo Configured owner SQLite connection.
     */
    public function __construct(private PDO $pdo) {}

    /**
     * Read one one-based offset page using the original UTC/date index and deterministic ordering.
     *
     * @param ActivityFilters $filters Owner-validated criteria.
     * @param int             $page    Verified one-based page number.
     *
     * @return array{
     *   events: list<array{id: int, actor_id: int, actor_label: string, action: string, target: string|null,
     *   occurred_at: string}>, hasNext: bool
     * }
     * @throws RuntimeException If the query cannot be prepared.
     * @throws PDOException If the query fails.
     */
    public function page(ActivityFilters $filters, int $page) : array
    {
        $predicates = $this->predicates(filters: $filters);
        $where      = $predicates['where'];
        $parameters = $predicates['parameters'];

        // The day expression index wins over actor-first on explicit date filters.
        $table = $filters->date !== null ? 'activity_events INDEXED BY idx_activity_day' : 'activity_events';
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
        $statement->bindValue(param: ':limit', value: Statistics::PAGE_SIZE + 1, type: PDO::PARAM_INT);
        $statement->bindValue(param: ':offset', value: ($page - 1) * Statistics::PAGE_SIZE, type: PDO::PARAM_INT);
        $statement->execute();

        /** @var list<array{id: int, actor_id: int, actor_label: string, action: string, target: string|null, occurred_at: string}> $rows */
        $rows = $statement->fetchAll(mode: PDO::FETCH_ASSOC);

        return [
            'events'  => array_slice(array: $rows, offset: 0, length: Statistics::PAGE_SIZE),
            'hasNext' => count(value: $rows) > Statistics::PAGE_SIZE,
        ];
    }

    /**
     * Read one bounded keyset slice, preserving exact direction, ties and index selection.
     *
     * @param ActivityFilters                                      $filters   Owner-validated criteria.
     * @param array{time: string, id: int, direction: string}|null $position  Verified cursor position.
     * @param string                                               $direction Current navigation direction.
     *
     * @return array{
     *   rows: list<array{id: int, actor_id: int, actor_label: string, action: string, target: string|null,
     *   occurred_at: string}>, hasMore: bool
     * }
     * @throws RuntimeException If the SQLite query cannot be prepared.
     * @throws PDOException If the SQLite statement fails.
     */
    public function cursorSlice(ActivityFilters $filters, array|null $position, string $direction) : array
    {
        $predicates = $this->predicates(filters: $filters);
        $where      = $predicates['where'];
        $parameters = $predicates['parameters'];

        if ($position !== null) {
            $comparison                     = $direction === 'after' ? '>' : '<';
            $where[]                        = '(occurred_at ' . $comparison . ' :cursor_time OR (occurred_at = :cursor_same_time AND id ' . $comparison . ' :cursor_id))';
            $parameters['cursor_time']      = $position['time'];
            $parameters['cursor_same_time'] = $position['time'];
        }

        $table = $filters->date !== null ? 'activity_events INDEXED BY idx_activity_day' : 'activity_events';
        $sql   = 'SELECT id, actor_id, actor_label, action, target, occurred_at FROM ' . $table;
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(separator: ' AND ', array: $where);
        }
        $sort      = $direction === 'after' ? 'ASC' : 'DESC';
        $sql       .= ' ORDER BY occurred_at ' . $sort . ', id ' . $sort . ' LIMIT :limit';
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
        $statement->bindValue(param: ':limit', value: Statistics::PAGE_SIZE + 1, type: PDO::PARAM_INT);
        $statement->execute();

        /** @var list<array{id: int, actor_id: int, actor_label: string, action: string, target: string|null, occurred_at: string}> $rows */
        $rows    = $statement->fetchAll(mode: PDO::FETCH_ASSOC);
        $hasMore = count(value: $rows) > Statistics::PAGE_SIZE;
        $rows    = array_slice(array: $rows, offset: 0, length: Statistics::PAGE_SIZE);
        if ($direction === 'after') {
            $rows = array_reverse(array: $rows);
        }

        return ['rows' => $rows, 'hasMore' => $hasMore];
    }

    /**
     * Convert normalized owner criteria into parameter-bound SQLite predicates.
     *
     * @param ActivityFilters $filters Canonical validated criteria.
     *
     * @return array{where: list<string>, parameters: array<string, string>}
     */
    private function predicates(ActivityFilters $filters) : array
    {
        $where      = [];
        $parameters = [];

        if ($filters->date !== null) {
            $where[]            = 'substr(occurred_at, 1, 10) = :date';
            $parameters['date'] = $filters->date->value();
        }

        if ($filters->username !== null) {
            $where[]            = 'actor_label = :user COLLATE NOCASE';
            $parameters['user'] = $filters->username;
        }

        if ($filters->action !== null) {
            $where[]              = 'action = :action';
            $parameters['action'] = $filters->action->value;
        }

        return ['where' => $where, 'parameters' => $parameters];
    }
}
