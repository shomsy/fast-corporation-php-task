<?php

declare(strict_types=1);

namespace InterviewTask\Tests\ActivityTracking;

use DateTimeImmutable;
use InterviewTask\ActivityTracking\DailyReports;
use InterviewTask\ActivityTracking\Statistics;
use InterviewTask\Infrastructure\Database\Migrator;
use InterviewTask\Infrastructure\Database\SqliteConnection;
use InvalidArgumentException;
use Override;
use PDO;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * Regression checks for Statistics reports test, covering observable behavior and failure paths.
 *
 * @package InterviewTask\Tests\ActivityTracking
 */
final class StatisticsReportsTest extends TestCase
{
    /**
     * Internal state or dependency owned by StatisticsReportsTest.
     *
     * @var PDO
     */
    private PDO          $pdo;
    /**
     * Internal state or dependency owned by StatisticsReportsTest.
     *
     * @var Statistics
     */
    private Statistics   $statistics;
    /**
     * Internal state or dependency owned by StatisticsReportsTest.
     *
     * @var DailyReports
     */
    private DailyReports $reports;

    /**
     * Verify statistics returns history in stable newest first order through the public behavior.
     *
     * @return void No return value.
     */
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

    /**
     * Verify statistics combines date user and action filters through the public behavior.
     *
     * @return void No return value.
     */
    public function testStatisticsCombinesDateUserAndActionFilters(): void
    {
        $events = $this->statistics->events(date: '2026-10-07', user: 'ALICE', action: 'view-page');

        self::assertCount(2, $events);
        self::assertSame('page-b', $events[0]['target']);
        self::assertSame('page-a', $events[1]['target']);
    }

    /**
     * Verify statistics returns empty for unknown user through the public behavior.
     *
     * @return void No return value.
     */
    public function testStatisticsReturnsEmptyForUnknownUser(): void
    {
        self::assertSame([], $this->statistics->events(user: 'missing'));
    }

    /**
     * Verify statistics rejects malformed or invalid date through the public behavior.
     *
     * @return void No return value.
     */
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

    /**
     * Verify an embedded NUL in a statistics date fails as expected input validation.
     *
     * @return void No return value.
     */
    public function testStatisticsRejectsNulDateAsInvalidInput(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->statistics->events(date: "2026-10-07\0");
    }

    /**
     * Verify both report date bounds reject embedded NUL as client input.
     *
     * @return void No return value.
     */
    public function testReportsRejectNulDateBoundsAsInvalidInput(): void
    {
        foreach (['from', 'to'] as $bound) {
            try {
                $this->reports->dailyCounts(
                    from: $bound === 'from' ? "2026-10-07\0" : null,
                    to: $bound === 'to' ? "2026-10-08\0" : null,
                );
                self::fail('A NUL-containing report date was accepted.');
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString('Invalid report date', $exception->getMessage());
            }
        }
    }

    /**
     * Verify statistics rejects unknown action through the public behavior.
     *
     * @return void No return value.
     */
    public function testStatisticsRejectsUnknownAction(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->statistics->events(action: 'delete-account');
    }

    /**
     * Verify statistics uses prepared statements for user filter through the public behavior.
     *
     * @return void No return value.
     */
    public function testStatisticsUsesPreparedStatementsForUserFilter(): void
    {
        self::assertSame([], $this->statistics->events(user: "' OR 1=1 --"));
    }

    /**
     * Verify reports have exact four daily counts through the public behavior.
     *
     * @return void No return value.
     */
    public function testReportsHaveExactFourDailyCounts(): void
    {
        self::assertSame([
            ['date' => '2026-10-07', 'page_a_views' => 2, 'page_b_views' => 1, 'buy_cow_clicks' => 1, 'download_clicks' => 1],
            ['date' => '2026-10-08', 'page_a_views' => 1, 'page_b_views' => 1, 'buy_cow_clicks' => 1, 'download_clicks' => 1],
        ], $this->reports->dailyCounts());
    }

    /**
     * Verify reports ignore login only dates through the public behavior.
     *
     * @return void No return value.
     */
    public function testReportsIgnoreLoginOnlyDates(): void
    {
        $statement = $this->pdo->prepare(
            query: "INSERT INTO activity_events (actor_id, actor_label, action, target, occurred_at) VALUES (3, 'charlie', 'login', NULL, '2026-10-06T10:00:00+00:00')",
        );
        self::assertNotFalse($statement);
        self::assertTrue($statement->execute());

        self::assertCount(2, $this->reports->dailyCounts());
    }

    /**
     * Verify reports return empty for no activity through the public behavior.
     *
     * @return void No return value.
     */
    public function testReportsReturnEmptyForNoActivity(): void
    {
        $this->pdo->exec(statement: 'DELETE FROM activity_events');
        self::assertSame([], $this->reports->dailyCounts());
    }

    /**
     * Verify same timestamp uses event id as tie breaker through the public behavior.
     *
     * @return void No return value.
     */
    public function testSameTimestampUsesEventIdAsTieBreaker(): void
    {
        $events = $this->statistics->events(date: '2026-10-07', action: 'view-page');

        self::assertCount(3, $events);
        self::assertSame(2, $events[1]['actor_id']);
        self::assertSame(1, $events[2]['actor_id']);
    }

    /**
     * Verify statistics pagination bounds rows and uses stable ordering through the public behavior.
     *
     * @return void No return value.
     */
    public function testStatisticsPaginationBoundsRowsAndUsesStableOrdering(): void
    {
        $insert = $this->pdo->prepare(
            query: "INSERT INTO activity_events (actor_id, actor_label, action, target, occurred_at) VALUES (1, 'alice', 'view-page', 'page-a', '2026-10-08T12:00:00+00:00')",
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

    /**
     * Verify statistics has no arbitrary fifty thousand event access ceiling through the public behavior.
     *
     * @return void No return value.
     */
    public function testStatisticsHasNoArbitraryFiftyThousandEventAccessCeiling(): void
    {
        self::assertSame([], $this->statistics->events(page: 1001));
        $this->expectException(InvalidArgumentException::class);
        $this->statistics->events(page: 0);
    }

    /**
     * Verify has next is false at exactly fifty and true at fifty one through the public behavior.
     *
     * @return void No return value.
     */
    public function testHasNextIsFalseAtExactlyFiftyAndTrueAtFiftyOne(): void
    {
        $this->pdo->exec(statement: 'DELETE FROM activity_events');
        $insert = $this->pdo->prepare(
            query: "INSERT INTO activity_events (actor_id, actor_label, action, target, occurred_at) VALUES (1, 'alice', 'view-page', 'page-a', '2026-10-08T12:00:00+00:00')",
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

    /**
     * Verify reports date range limits dataset through the public behavior.
     *
     * @return void No return value.
     */
    public function testReportsDateRangeLimitsDataset(): void
    {
        self::assertSame([
            ['date' => '2026-10-08', 'page_a_views' => 1, 'page_b_views' => 1, 'buy_cow_clicks' => 1, 'download_clicks' => 1],
        ], $this->reports->dailyCounts(from: '2026-10-08', to: '2026-10-08'));
    }

    /**
     * Verify reports reject invalid date range through the public behavior.
     *
     * @return void No return value.
     */
    public function testReportsRejectInvalidDateRange(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->reports->dailyCounts(from: '2026-10-09', to: '2026-10-08');
    }

    /**
     * Verify reports bound number of days through the public behavior.
     *
     * @return void No return value.
     */
    public function testReportsBoundNumberOfDays(): void
    {
        $stmt = $this->pdo->prepare(
            query: "INSERT INTO activity_events (actor_id, actor_label, action, target, occurred_at) VALUES (1, 'alice', 'view-page', 'page-a', ?)",
        );
        self::assertNotFalse($stmt);
        for ($i = 1; $i <= 40; ++$i) {
            $date = (new DateTimeImmutable(datetime: '2026-09-01T01:00:00+00:00'))->modify(modifier: '+' . ($i - 1) . ' days');
            self::assertTrue($stmt->execute(params: [$date->format(format: 'c')]));
        }
        self::assertLessThanOrEqual(DailyReports::MAX_DAYS, count(value: $this->reports->dailyCounts()));
    }

    /**
     * Verify representative five thousand event report is bounded and indexed through the public behavior.
     *
     * @return void No return value.
     */
    public function testRepresentativeFiveThousandEventReportIsBoundedAndIndexed(): void
    {
        $this->pdo->beginTransaction();
        try {
            $insert = $this->pdo->prepare(
                query: "INSERT INTO activity_events (actor_id, actor_label, action, target, occurred_at) VALUES (1, 'alice', 'view-page', 'page-a', ?)",
            );
            self::assertNotFalse($insert);
            $start = new DateTimeImmutable(datetime: '2026-08-01T01:00:00+00:00');
            for ($i = 0; $i < 5000; ++$i) {
                $date = $start->modify(modifier: '+' . ($i % 60) . ' days');
                self::assertTrue($insert->execute(params: [$date->format(format: 'c')]));
            }
            $this->pdo->commit();
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }

        $daily = $this->reports->dailyCounts();
        self::assertCount(DailyReports::MAX_DAYS, $daily);
        self::assertSame('2026-09-02', $daily[0]['date']);
        self::assertSame('2026-10-08', $daily[29]['date']);

        $plan = $this->pdo->query(
            query: "EXPLAIN QUERY PLAN SELECT substr(occurred_at, 1, 10) AS date, COUNT(*) FROM activity_events WHERE action = 'view-page' AND target = 'page-a' AND substr(occurred_at, 1, 10) >= '2026-09-01' GROUP BY substr(occurred_at, 1, 10) ORDER BY date DESC LIMIT 30",
        );
        self::assertNotFalse($plan);
        $details = implode(separator: ' ', array: array_map(callback: static fn(mixed $row): string => is_array(value: $row) && isset($row['detail']) && is_string(value: $row['detail']) ? $row['detail'] : '', array: $plan->fetchAll(mode: PDO::FETCH_ASSOC)));
        self::assertStringContainsString('idx_activity_day', $details);
    }

    /**
     * Verify measured activity indexes support newest first and daily group plans through the public behavior.
     *
     * @return void No return value.
     */
    public function testMeasuredActivityIndexesSupportNewestFirstAndDailyGroupPlans(): void
    {
        $recentPlan = $this->pdo->query(
            query: 'EXPLAIN QUERY PLAN SELECT id FROM activity_events ORDER BY occurred_at DESC, id DESC LIMIT 50',
        );
        self::assertNotFalse($recentPlan);
        $recentDetails = implode(separator: ' ', array: array_map(callback: static fn(mixed $detail): string => is_string(value: $detail) ? $detail : '', array: array_column(array: $recentPlan->fetchAll(mode: PDO::FETCH_ASSOC), column_key: 'detail')));
        self::assertStringContainsString('idx_activity_recent', $recentDetails);

        $dailyPlan = $this->pdo->query(
            query: 'EXPLAIN QUERY PLAN SELECT substr(occurred_at, 1, 10) AS day, COUNT(*) FROM activity_events GROUP BY substr(occurred_at, 1, 10) ORDER BY day DESC LIMIT 30',
        );
        self::assertNotFalse($dailyPlan);
        $dailyDetails = implode(separator: ' ', array: array_map(callback: static fn(mixed $detail): string => is_string(value: $detail) ? $detail : '', array: array_column(array: $dailyPlan->fetchAll(mode: PDO::FETCH_ASSOC), column_key: 'detail')));
        self::assertStringContainsString('idx_activity_day', $dailyDetails);
    }

    /**
     * Build isolated fixtures for this test case.
     *
     * @return void No return value.
     */
    #[Override]

    protected function setUp(): void
    {
        $this->pdo = SqliteConnection::memory();
        (new Migrator(pdo: $this->pdo, migrationDirectories: [
            'ActivityTracking' => dirname(path: __DIR__, levels: 2) . '/src/ActivityTracking/migrations',
        ]))->migrate();

        $this->statistics = new Statistics(pdo: $this->pdo);
        $this->reports    = new DailyReports(pdo: $this->pdo);

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
                query: 'INSERT INTO activity_events (actor_id, actor_label, action, target, occurred_at) VALUES (?, ?, ?, ?, ?)',
            );
            self::assertNotFalse($statement);
            self::assertTrue($statement->execute(params: $record));
        }
    }

}
