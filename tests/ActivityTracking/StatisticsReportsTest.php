<?php

declare(strict_types=1);

namespace InterviewTask\Tests\ActivityTracking;

use DateTimeImmutable;
use InterviewTask\ActivityTracking\DailyReports;
use InterviewTask\ActivityTracking\Statistics;
use InterviewTask\Infrastructure\Database\Migrator;
use InterviewTask\Infrastructure\Database\SqliteConnection;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;

final class StatisticsReportsTest extends TestCase
{
    private PDO          $pdo;
    private Statistics   $statistics;
    private DailyReports $reports;

    public function testStatisticsReturnsHistoryInStableNewestFirstOrder() : void
    {
        $events = $this->statistics->events();

        self::assertCount(10, $events);
        self::assertSame('2026-10-08T12:00:00+00:00', $events[0]['occurred_at']);
        self::assertSame('bob', $events[0]['actor_label']);
        self::assertSame('download', $events[5]['target']);
        self::assertSame('bob', $events[8]['actor_label']);
        self::assertSame('alice', $events[9]['actor_label']);
    }

    public function testStatisticsCombinesDateUserAndActionFilters() : void
    {
        $events = $this->statistics->events('2026-10-07', 'ALICE', 'view-page');

        self::assertCount(2, $events);
        self::assertSame('page-b', $events[0]['target']);
        self::assertSame('page-a', $events[1]['target']);
    }

    public function testStatisticsReturnsEmptyForUnknownUser() : void
    {
        self::assertSame([], $this->statistics->events(user: 'missing'));
    }

    public function testStatisticsRejectsMalformedOrInvalidDate() : void
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

    public function testStatisticsRejectsUnknownAction() : void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->statistics->events(action: 'delete-account');
    }

    public function testStatisticsUsesPreparedStatementsForUserFilter() : void
    {
        self::assertSame([], $this->statistics->events(user: "' OR 1=1 --"));
    }

    public function testReportsHaveExactFourDailyCounts() : void
    {
        self::assertSame([
                             ['date' => '2026-10-07', 'page_a_views' => 2, 'page_b_views' => 1, 'buy_cow_clicks' => 1, 'download_clicks' => 1],
                             ['date' => '2026-10-08', 'page_a_views' => 1, 'page_b_views' => 1, 'buy_cow_clicks' => 1, 'download_clicks' => 1],
                         ], $this->reports->dailyCounts());
    }

    public function testReportsIgnoreLoginOnlyDates() : void
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO activity_events (actor_id, actor_label, action, target, occurred_at) VALUES (3, 'charlie', 'login', NULL, '2026-10-06T10:00:00+00:00')",
        );
        self::assertNotFalse($statement);
        self::assertTrue($statement->execute());

        self::assertCount(2, $this->reports->dailyCounts());
    }

    public function testReportsReturnEmptyForNoActivity() : void
    {
        $this->pdo->exec('DELETE FROM activity_events');
        self::assertSame([], $this->reports->dailyCounts());
    }

    public function testSameTimestampUsesEventIdAsTieBreaker() : void
    {
        $events = $this->statistics->events('2026-10-07', action: 'view-page');

        self::assertCount(3, $events);
        self::assertSame(2, $events[1]['actor_id']);
        self::assertSame(1, $events[2]['actor_id']);
    }

    public function testStatisticsPaginationBoundsRowsAndUsesStableOrdering() : void
    {
        $insert = $this->pdo->prepare(
            "INSERT INTO activity_events (actor_id, actor_label, action, target, occurred_at) VALUES (1, 'alice', 'view-page', 'page-a', '2026-10-08T12:00:00+00:00')",
        );
        self::assertNotFalse($insert);
        for ($i = 0; $i < 105; ++$i) {
            $insert->execute();
        }

        $first  = $this->statistics->events(page: 1);
        $second = $this->statistics->events(page: 2);
        $third  = $this->statistics->events(page: 3);
        self::assertCount(50, $first);
        self::assertCount(50, $second);
        self::assertCount(15, $third);
        self::assertGreaterThan($second[0]['id'], $first[49]['id']);
        self::assertGreaterThan($third[0]['id'], $second[49]['id']);
    }

    public function testStatisticsHasNoArbitraryFiftyThousandEventAccessCeiling() : void
    {
        self::assertSame([], $this->statistics->events(page: 1001));
        $this->expectException(InvalidArgumentException::class);
        $this->statistics->events(page: 0);
    }

    public function testHasNextIsFalseAtExactlyFiftyAndTrueAtFiftyOne() : void
    {
        $this->pdo->exec('DELETE FROM activity_events');
        $insert = $this->pdo->prepare(
            "INSERT INTO activity_events (actor_id, actor_label, action, target, occurred_at) VALUES (1, 'alice', 'view-page', 'page-a', '2026-10-08T12:00:00+00:00')",
        );
        self::assertNotFalse($insert);
        for ($i = 0; $i < Statistics::PAGE_SIZE; ++$i) {
            self::assertTrue($insert->execute());
        }

        $first = $this->statistics->page();
        self::assertCount(50, $first['events']);
        self::assertFalse($first['hasNext']);

        self::assertTrue($insert->execute());
        $next = $this->statistics->page();
        self::assertCount(50, $next['events']);
        self::assertTrue($next['hasNext']);
        $last = $this->statistics->page(page: 2);
        self::assertCount(1, $last['events']);
        self::assertFalse($last['hasNext']);
    }

    public function testReportsDateRangeLimitsDataset() : void
    {
        self::assertSame([
                             ['date' => '2026-10-08', 'page_a_views' => 1, 'page_b_views' => 1, 'buy_cow_clicks' => 1, 'download_clicks' => 1],
                         ], $this->reports->dailyCounts('2026-10-08', '2026-10-08'));
    }

    public function testReportsRejectInvalidDateRange() : void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->reports->dailyCounts('2026-10-09', '2026-10-08');
    }

    public function testReportsBoundNumberOfDays() : void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO activity_events (actor_id, actor_label, action, target, occurred_at) VALUES (1, 'alice', 'view-page', 'page-a', ?)",
        );
        self::assertNotFalse($stmt);
        for ($i = 1; $i <= 40; ++$i) {
            $date = (new DateTimeImmutable('2026-09-01T01:00:00+00:00'))->modify('+' . ($i - 1) . ' days');
            self::assertTrue($stmt->execute([$date->format('c')]));
        }
        self::assertLessThanOrEqual(DailyReports::MAX_DAYS, count($this->reports->dailyCounts()));
    }

    public function testRepresentativeFiveThousandEventReportIsBoundedAndIndexed() : void
    {
        $this->pdo->beginTransaction();
        try {
            $insert = $this->pdo->prepare(
                "INSERT INTO activity_events (actor_id, actor_label, action, target, occurred_at) VALUES (1, 'alice', 'view-page', 'page-a', ?)",
            );
            self::assertNotFalse($insert);
            $start = new DateTimeImmutable('2026-08-01T01:00:00+00:00');
            for ($i = 0; $i < 5000; ++$i) {
                $date = $start->modify('+' . ($i % 60) . ' days');
                self::assertTrue($insert->execute([$date->format('c')]));
            }
            $this->pdo->commit();
        } catch (\Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }

        $daily = $this->reports->dailyCounts();
        self::assertCount(DailyReports::MAX_DAYS, $daily);
        self::assertSame('2026-09-02', $daily[0]['date']);
        self::assertSame('2026-10-08', $daily[29]['date']);

        $plan = $this->pdo->query(
            "EXPLAIN QUERY PLAN SELECT substr(occurred_at, 1, 10) AS date, COUNT(*) FROM activity_events WHERE action = 'view-page' AND target = 'page-a' AND substr(occurred_at, 1, 10) >= '2026-09-01' GROUP BY substr(occurred_at, 1, 10) ORDER BY date DESC LIMIT 30",
        );
        self::assertNotFalse($plan);
        $details = implode(' ', array_map(static fn (mixed $row) : string => is_array($row) && isset($row['detail']) && is_string($row['detail']) ? $row['detail'] : '', $plan->fetchAll(PDO::FETCH_ASSOC)));
        self::assertStringContainsString('idx_activity_day', $details);
    }

    public function testMeasuredActivityIndexesSupportNewestFirstAndDailyGroupPlans() : void
    {
        $recentPlan = $this->pdo->query(
            'EXPLAIN QUERY PLAN SELECT id FROM activity_events ORDER BY occurred_at DESC, id DESC LIMIT 50',
        );
        self::assertNotFalse($recentPlan);
        $recentDetails = implode(' ', array_map(static fn (mixed $detail) : string => is_string($detail) ? $detail : '', array_column($recentPlan->fetchAll(PDO::FETCH_ASSOC), 'detail')));
        self::assertStringContainsString('idx_activity_recent', $recentDetails);

        $dailyPlan = $this->pdo->query(
            'EXPLAIN QUERY PLAN SELECT substr(occurred_at, 1, 10) AS day, COUNT(*) FROM activity_events GROUP BY substr(occurred_at, 1, 10) ORDER BY day DESC LIMIT 30',
        );
        self::assertNotFalse($dailyPlan);
        $dailyDetails = implode(' ', array_map(static fn (mixed $detail) : string => is_string($detail) ? $detail : '', array_column($dailyPlan->fetchAll(PDO::FETCH_ASSOC), 'detail')));
        self::assertStringContainsString('idx_activity_day', $dailyDetails);
    }

    protected function setUp() : void
    {
        $this->pdo = SqliteConnection::memory();
        (new Migrator($this->pdo, [
            'ActivityTracking' => dirname(__DIR__, 2) . '/src/ActivityTracking/migrations',
        ]))->migrate();

        $this->statistics = new Statistics($this->pdo);
        $this->reports    = new DailyReports($this->pdo);

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

}
