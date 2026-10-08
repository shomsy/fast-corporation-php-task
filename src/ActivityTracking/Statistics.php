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
        return $this->page(date: $date, user: $user, action: $action, page: $page)['events'];
    }

    /**
     * One extra row proves whether an older page really exists.
     *
     * @return array{events: list<array{id: int, actor_id: int, actor_label: string, action: string, target: ?string, occurred_at: string}>, hasNext: bool}
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

        /** @var list<array{id: int, actor_id: int, actor_label: string, action: string, target: ?string, occurred_at: string}> $rows */
        $rows = $statement->fetchAll(mode: PDO::FETCH_ASSOC);

        return [
            'events'  => array_slice(array: $rows, offset: 0, length: self::PAGE_SIZE),
            'hasNext' => count(value: $rows) > self::PAGE_SIZE,
        ];
    }
}
