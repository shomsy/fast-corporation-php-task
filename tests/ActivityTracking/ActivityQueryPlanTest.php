<?php

declare(strict_types=1);

namespace InterviewTask\Tests\ActivityTracking;

use InterviewTask\Infrastructure\Database\Migrator;
use InterviewTask\Infrastructure\Database\SqliteConnection;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Regression coverage for planned actor and date history access paths.
 *
 * @package InterviewTask\Tests\ActivityTracking
 */
final class ActivityQueryPlanTest extends TestCase
{
    /**
     * Rare-actor filtering must have an actor-first access path without changing date plans.
     *
     * @return void No return value.
     */
    public function testSelectiveActorAndUtcDateIndexes() : void
    {
        $pdo = SqliteConnection::memory();
        (new Migrator(pdo: $pdo, migrationDirectories: [
            'ActivityTracking' => dirname(path: __DIR__, levels: 2) . '/src/ActivityTracking/migrations',
        ]))->migrate();

        $actor = $pdo->query(
            query: "EXPLAIN QUERY PLAN SELECT id FROM activity_events WHERE actor_label = 'rare' COLLATE NOCASE ORDER BY occurred_at DESC, id DESC LIMIT 51",
        );
        self::assertNotFalse($actor);
        $actorPlan = $actor->fetchAll(mode: PDO::FETCH_ASSOC);
        self::assertStringContainsString('idx_activity_actor_recent', implode(separator: ' ', array: array_map(callback: static fn (mixed $value) : string => is_string(value: $value) ? $value : '', array: array_column(array: $actorPlan, column_key: 'detail'))));

        $date = $pdo->query(
            query: "EXPLAIN QUERY PLAN SELECT id FROM activity_events INDEXED BY idx_activity_day WHERE actor_label = 'common' COLLATE NOCASE AND substr(occurred_at,1,10) = '2026-10-07' ORDER BY occurred_at DESC, id DESC LIMIT 51",
        );
        self::assertNotFalse($date);
        $datePlan = $date->fetchAll(mode: PDO::FETCH_ASSOC);
        self::assertStringContainsString('idx_activity_day', implode(separator: ' ', array: array_map(callback: static fn (mixed $value) : string => is_string(value: $value) ? $value : '', array: array_column(array: $datePlan, column_key: 'detail'))));
    }
}
