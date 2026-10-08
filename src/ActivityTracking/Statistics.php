<?php

declare(strict_types=1);

namespace InterviewTask\ActivityTracking;

use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/** Stable, bounded and newest-first activity history. */
final readonly class Statistics
{
    public const int PAGE_SIZE = 50;

    public function __construct(private PDO $pdo) {}

    /**
     * @return list<array{id: int, actor_id: int, actor_label: string, action: string, target: ?string, occurred_at: string}>
     */
    public function events(?string $date = null, ?string $user = null, ?string $action = null, int $page = 1): array
    {
        if ($page < 1 || $page > 1000) {
            throw new InvalidArgumentException('Invalid page.');
        }

        $where = [];
        $parameters = [];

        if ($date !== null && $date !== '') {
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

            if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
                throw new InvalidArgumentException('Invalid date. Expected YYYY-MM-DD.');
            }

            $where[] = 'substr(occurred_at, 1, 10) = :date';
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

        $sql .= ' ORDER BY occurred_at DESC, id DESC LIMIT :limit OFFSET :offset';

        $statement = $this->pdo->prepare($sql);

        if ($statement === false) {
            throw new RuntimeException('Unable to prepare activity statistics query.');
        }

        foreach ($parameters as $key => $value) {
            $statement->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', self::PAGE_SIZE, PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * self::PAGE_SIZE, PDO::PARAM_INT);
        $statement->execute();

        /** @var list<array{id: int, actor_id: int, actor_label: string, action: string, target: ?string, occurred_at: string}> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
    }
}
