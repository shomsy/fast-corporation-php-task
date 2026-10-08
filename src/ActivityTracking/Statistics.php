<?php

declare(strict_types=1);

namespace InterviewTask\ActivityTracking;

use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Filtered, newest-first activity history. The label is a historical snapshot,
 * not a join against Accounts' private storage.
 */
final readonly class Statistics
{
    public function __construct(private PDO $pdo) {}

    /**
     * @return list<array{id: int, actor_id: int, actor_label: string, action: string, target: ?string, occurred_at: string}>
     */
    public function events(?string $date = null, ?string $user = null, ?string $action = null): array
    {
        $where = [];
        $parameters = [];

        if ($date !== null && $date !== '') {
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

            if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
                throw new InvalidArgumentException('Invalid date. Expected YYYY-MM-DD.');
            }

            // Timestamps are persisted as UTC ISO 8601 strings.
            $where[] = "substr(occurred_at, 1, 10) = :date";
            $parameters['date'] = $date;
        }

        if ($user !== null && trim($user) !== '') {
            $where[] = 'actor_label = :user COLLATE NOCASE';
            $parameters['user'] = trim($user);
        }

        if ($action !== null && $action !== '') {
            $validated = ActivityAction::tryFrom($action);

            if ($validated === null) {
                throw new InvalidArgumentException('Unknown activity action.');
            }

            $where[] = 'action = :action';
            $parameters['action'] = $validated->value;
        }

        $sql = 'SELECT id, actor_id, actor_label, action, target, occurred_at FROM activity_events';

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY occurred_at DESC, id DESC';

        $statement = $this->pdo->prepare($sql);

        if ($statement === false) {
            throw new RuntimeException('Unable to prepare activity statistics query.');
        }

        $statement->execute($parameters);

        /** @var list<array{id: int, actor_id: int, actor_label: string, action: string, target: ?string, occurred_at: string}> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
    }
}
