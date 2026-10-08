<?php

declare(strict_types=1);

namespace InterviewTask\Tests\ActivityTracking;

use InterviewTask\ActivityTracking\DailyReports;
use InterviewTask\ActivityTracking\Statistics;
use InterviewTask\Infrastructure\Database\Migrator;
use InterviewTask\Infrastructure\Database\SqliteConnection;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;

final class StatisticsReportsTest extends TestCase
{
    private PDO $pdo;
    private Statistics $statistics;
    private DailyReports $reports;

    protected function setUp(): void
    {
        $this->pdo = SqliteConnection::memory();
        (new Migrator($this->pdo, [
            'ActivityTracking' => dirname(__DIR__, 2) . '/src/ActivityTracking/migrations',
        ]))->migrate();

        $this->statistics = new Statistics($this->pdo);
        $this->reports = new DailyReports($this->pdo);

        foreach ([
            [1, 'alice', 'view-page', 'page-a', '2026-10-07T10:00:00+00:00'],
            [2, 'bob', 'view-page', 'page-a', '2026-10-07T10:00:00+00:00'],
            [1, 'alice', 'view-page', 'page-b', '2026-10-07T11:00:00+00:00'],
            [1, 'alice', 'button-click', 'buy-cow', '2026-10-07T12:00:00+00:00'],
            [2, 'bob', 'button-click', 'download', '2026-10-07T13:00:00+00:00'],
            [2, 'bob', 'login', null, '2026-10-07T14:00:00+00:00'],
            [1, 'alice', 'view-page', 'page-a', '2026-10-08T09:00:00+00:00'],
            [1, 'alice', 'button-click', 'download', '2026-10-08T10:00:00+00:00'],
            [2, 'bob', 'view-page', 'page-b', '2026-10-08T11:00:00+00:00'],
            [2, 'bob', 'button-click', 'buy-cow', '2026-10-08T12:00:00+00:00'],
        ] as $record) {
            $statement = $this->pdo->prepare(
                'INSERT INTO activity_events (actor_id, actor_label, action, target, occurred_at) VALUES (?, ?, ?, ?, ?)',
            );
            self::assertNotFalse($statement);
            self::assertTrue($statement->execute($record));
        }
    }

    public function testStatisticsReturnsHistoryInStableNewestFirstOrder(): void
    {
        $events = $this->statistics->events();

        self::assertCount(10, $events);
        self::assertSame('2026-10-08T12:00:00+00:00', $events[0]['occurred_at']);
        self::assertSame('bob', $events[0]['actor_label']);
        self::assertSame('download', $events[5]['target']);
        self::assertSame('bob', $events[8]['actor_label']);
        self::assertSame('alice', $events[9]['actor_label']);
    }

    public function testStatisticsCombinesDateUserAndActionFilters(): void
    {
        $events = $this->statistics->events('2026-10-07', 'ALICE', 'view-page');

        self::assertCount(2, $events);
        self::assertSame('page-b', $events[0]['target']);
        self::assertSame('page-a', $events[1]['target']);
    }

    public function testStatisticsReturnsEmptyForUnknownUser(): void
    {
        self::assertSame([], $this->statistics->events(user: 'missing'));
    }

    public function testStatisticsRejectsMalformedOrInvalidDate(): void
    {
        foreach (['2026-02-30', '2026-8-1', "2026-10-07' OR 1=1 --"] as $date) {
            try {
                $this->statistics->events(date: $date);
                self::fail('Invalid date was accepted: ' . $date);
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString('Invalid date', $exception->getMessage());
            }
        }
    }

    public function testStatisticsRejectsUnknownAction(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->statistics->events(action: 'delete-account');
    }

    public function testStatisticsUsesPreparedStatementsForUserFilter(): void
    {
        self::assertSame([], $this->statistics->events(user: "' OR 1=1 --"));
    }

    public function testReportsHaveExactFourDailyCounts(): void
    {
        self::assertSame([
            ['date' => '2026-10-07', 'page_a_views' => 2, 'page_b_views' => 1, 'buy_cow_clicks' => 1, 'download_clicks' => 1],
            ['date' => '2026-10-08', 'page_a_views' => 1, 'page_b_views' => 1, 'buy_cow_clicks' => 1, 'download_clicks' => 1],
        ], $this->reports->dailyCounts());
    }

    public function testReportsIgnoreLoginOnlyDates(): void
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO activity_events (actor_id, actor_label, action, target, occurred_at) VALUES (3, 'charlie', 'login', NULL, '2026-10-06T10:00:00+00:00')",
        );
        self::assertNotFalse($statement);
        self::assertTrue($statement->execute());

        self::assertCount(2, $this->reports->dailyCounts());
    }

    public function testReportsReturnEmptyForNoActivity(): void
    {
        $this->pdo->exec('DELETE FROM activity_events');
        self::assertSame([], $this->reports->dailyCounts());
    }

    public function testSameTimestampUsesEventIdAsTieBreaker(): void
    {
        $events = $this->statistics->events('2026-10-07', action: 'view-page');

        self::assertCount(3, $events);
        self::assertSame(2, $events[1]['actor_id']);
        self::assertSame(1, $events[2]['actor_id']);
    }
}
