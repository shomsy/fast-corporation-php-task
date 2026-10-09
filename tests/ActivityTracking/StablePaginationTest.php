<?php

declare(strict_types=1);

namespace InterviewTask\Tests\ActivityTracking;

use InterviewTask\ActivityTracking\Statistics;
use InterviewTask\ActivityTracking\Persistence\ReadActivityHistory;
use InterviewTask\Infrastructure\Database\Migrator;
use InterviewTask\Infrastructure\Database\SqliteConnection;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Verify cursor navigation remains stable while event history grows.
 *
 * @package InterviewTask\Tests\ActivityTracking
 */
final class StablePaginationTest extends TestCase
{
    /**
     * New rows and identical timestamps must not shift existing cursor pages.
     *
     * @return void No return value.
     */
    public function testStableTraversalSurvivesNewEventsAndTimestampTies() : void
    {
        $pdo = $this->database();
        $this->seed(pdo: $pdo, count: 115);
        $statistics = new Statistics(reader: new ReadActivityHistory(pdo: $pdo));
        $key        = 'test-session-only-secret';
        $first      = $statistics->cursorPage(key: $key);
        self::assertCount(50, $first['events']);
        self::assertNotNull($first['nextCursor']);
        $firstIds = array_column(array: $first['events'], column_key: 'id');

        $this->seed(pdo: $pdo, count: 5);
        $second = $statistics->cursorPage(cursor: $first['nextCursor'], key: $key);
        self::assertCount(50, $second['events']);
        self::assertSame([], array_intersect($firstIds, array_column(array: $second['events'], column_key: 'id')));
        self::assertNotNull($second['previousCursor']);

        $third = $statistics->cursorPage(cursor: $second['nextCursor'], key: $key);
        self::assertCount(15, $third['events']);
        self::assertNull($third['nextCursor']);

        $back = $statistics->cursorPage(cursor: $second['previousCursor'], key: $key);
        self::assertSame($firstIds, array_column(array: $back['events'], column_key: 'id'));
    }

    /**
     * Changing a signed cursor, filters or session invalidates the traversal.
     *
     * @return void No return value.
     */
    public function testCursorValidationBindsFiltersAndSession() : void
    {
        $pdo = $this->database();
        $this->seed(pdo: $pdo, count: 61);
        $statistics = new Statistics(reader: new ReadActivityHistory(pdo: $pdo));
        $first      = $statistics->cursorPage(key: 'first-key', action: 'view-page');
        $cursor     = $first['nextCursor'] ?? throw new \LogicException('Expected a next cursor.');

        foreach ([
                     static fn () : array => $statistics->cursorPage(cursor: substr(string: $cursor, offset: 0, length: -1) . 'x', key: 'first-key', action: 'view-page'),
                     static fn () : array => $statistics->cursorPage(cursor: $cursor, key: 'second-key', action: 'view-page'),
                     static fn () : array => $statistics->cursorPage(cursor: $cursor, key: 'first-key', action: 'login'),
                     static fn () : array => $statistics->cursorPage(cursor: 'not-a-valid-cursor', key: 'first-key'),
                 ] as $attempt) {
            try {
                $attempt();
                self::fail('Tampered or cross-filter cursor must be rejected.');
            } catch (InvalidArgumentException) {
                self::assertNotEmpty($cursor);
            }
        }
    }

    /**
     * Prepare an isolated in-memory ActivityTracking owner database.
     *
     * @return PDO Migrated SQLite connection.
     */
    private function database() : PDO
    {
        $pdo = SqliteConnection::memory();
        (new Migrator(pdo: $pdo, migrationDirectories: [
            'ActivityTracking' => dirname(path: __DIR__, levels: 2) . '/src/ActivityTracking/migrations',
        ]))->migrate();

        return $pdo;
    }

    /**
     * Append valid events with the same UTC timestamp.
     *
     * @param PDO $pdo   Target migrated database.
     * @param int $count Rows to append.
     *
     * @return void No return value.
     */
    private function seed(PDO $pdo, int $count) : void
    {
        $insert = $pdo->prepare(query: "INSERT INTO activity_events (actor_id, actor_label, action, target, occurred_at) VALUES (1, 'alice', 'view-page', 'page-a', '2026-10-09T12:00:00+00:00')");
        self::assertNotFalse($insert);
        for ($index = 0; $index < $count; $index++) {
            self::assertTrue($insert->execute());
        }
    }
}
