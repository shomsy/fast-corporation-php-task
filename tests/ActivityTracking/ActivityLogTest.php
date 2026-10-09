<?php

declare(strict_types=1);

namespace InterviewTask\Tests\ActivityTracking;

use DateTimeImmutable;
use DateTimeZone;
use InterviewTask\ActivityTracking\ActivityAction;
use InterviewTask\ActivityTracking\ActivityLog;
use InterviewTask\ActivityTracking\Persistence\RecordActivity;
use InterviewTask\ActivityTracking\TrackedButton;
use InterviewTask\ActivityTracking\TrackedPage;
use InterviewTask\Infrastructure\Database\Migrator;
use InterviewTask\Infrastructure\Database\SqliteConnection;
use InterviewTask\Infrastructure\Database\Transaction;
use InvalidArgumentException;
use Override;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

/**
 * Regression checks for Activity log test, covering observable behavior and failure paths.
 *
 * @package InterviewTask\Tests\ActivityTracking
 */
final class ActivityLogTest extends TestCase
{
    /**
     * Internal state or dependency owned by ActivityLogTest.
     *
     * @var PDO
     */
    private PDO $pdo;
    /**
     * Internal state or dependency owned by ActivityLogTest.
     *
     * @var ActivityLog
     */
    private ActivityLog $log;

    /**
     * Verify records all required events with historical actor and utc timestamp through the public behavior.
     *
     * @return void No return value.
     */
    public function testRecordsAllRequiredEventsWithHistoricalActorAndUtcTimestamp() : void
    {
        $this->log->recordRegistration(actorId: 7, actorLabel: 'alice');
        $this->log->recordLogin(actorId: 7, actorLabel: 'alice');
        $this->log->recordPageView(actorId: 7, actorLabel: 'alice', page: TrackedPage::PageA);
        $this->log->recordPageView(actorId: 7, actorLabel: 'alice', page: TrackedPage::PageB);
        $this->log->recordButtonClick(actorId: 7, actorLabel: 'alice', button: TrackedButton::BuyCow);
        $this->log->recordButtonClick(actorId: 7, actorLabel: 'alice', button: TrackedButton::Download);
        $this->log->recordLogout(actorId: 7, actorLabel: 'alice');

        $statement = $this->pdo->query(query: 'SELECT actor_id, actor_label, action, target, occurred_at FROM activity_events ORDER BY id');
        self::assertNotFalse($statement);
        $rows = $statement->fetchAll(mode: PDO::FETCH_ASSOC);
        self::assertCount(7, $rows);

        $expected = [
            [ActivityAction::Registration->value, null],
            [ActivityAction::Login->value, null],
            [ActivityAction::ViewPage->value, TrackedPage::PageA->value],
            [ActivityAction::ViewPage->value, TrackedPage::PageB->value],
            [ActivityAction::ButtonClick->value, TrackedButton::BuyCow->value],
            [ActivityAction::ButtonClick->value, TrackedButton::Download->value],
            [ActivityAction::Logout->value, null],
        ];

        foreach ($rows as $index => $row) {
            self::assertIsArray($row);
            self::assertSame($expected[$index], [$row['action'], $row['target']]);
            self::assertSame(7, $row['actor_id']);
            self::assertSame('alice', $row['actor_label']);
            self::assertSame('2026-10-08T10:15:00+00:00', $row['occurred_at']);
        }
    }

    /**
     * Verify does not read accounts or change earlier actor labels through the public behavior.
     *
     * @return void No return value.
     */
    public function testDoesNotReadAccountsOrChangeEarlierActorLabels() : void
    {
        $this->log->recordLogin(actorId: 14, actorLabel: 'old-label');
        $this->log->recordLogout(actorId: 14, actorLabel: 'new-label');

        $statement = $this->pdo->query(query: 'SELECT actor_label FROM activity_events ORDER BY id');
        self::assertNotFalse($statement);
        self::assertSame(['old-label', 'new-label'], $statement->fetchAll(mode: PDO::FETCH_COLUMN));
    }

    /**
     * Verify rejects invalid actor before writing through the public behavior.
     *
     * @return void No return value.
     */
    public function testRejectsInvalidActorBeforeWriting() : void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->log->recordLogin(actorId: 0, actorLabel: 'alice');
    }

    /**
     * Verify rejects blank actor label before writing through the public behavior.
     *
     * @return void No return value.
     */
    public function testRejectsBlankActorLabelBeforeWriting() : void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->log->recordLogin(actorId: 1, actorLabel: '  ');
    }

    /**
     * Verify database rejects invalid event taxonomy through the public behavior.
     *
     * @return void No return value.
     */
    public function testDatabaseRejectsInvalidEventTaxonomy() : void
    {
        $this->expectException(PDOException::class);
        $this->pdo->exec(statement: "INSERT INTO activity_events (actor_id, actor_label, action, target, occurred_at) VALUES (1, 'alice', 'button-click', 'page-a', '2026-10-08T10:15:00+00:00')");
    }

    /**
     * Verify participates in same local transaction and rolls back through the public behavior.
     *
     * @return void No return value.
     */
    public function testParticipatesInSameLocalTransactionAndRollsBack() : void
    {
        try {
            (new Transaction(pdo: $this->pdo))->run(operation: function () : void {
                $this->log->recordRegistration(actorId: 5, actorLabel: 'alice');
                $this->pdo->exec(statement: "INSERT INTO activity_events (actor_id, actor_label, action, target, occurred_at) VALUES (5, 'alice', 'view-page', 'buy-cow', '2026-10-08T10:15:00+00:00')");
            });
        } catch (PDOException) {
            // The second write violates event taxonomy; first write must roll back.
        }
        $statement = $this->pdo->query(query: 'SELECT COUNT(*) FROM activity_events');
        self::assertNotFalse($statement);
        self::assertSame(0, (int) $statement->fetchColumn());
    }

    /**
     * Build isolated fixtures for this test case.
     *
     * @return void No return value.
     */
    #[Override]
    protected function setUp() : void
    {
        $this->pdo = SqliteConnection::memory();

        (new Migrator(pdo: $this->pdo, migrationDirectories: [
            'ActivityTracking' => dirname(path: __DIR__, levels: 2) . '/src/ActivityTracking/migrations',
        ]))->migrate();

        /** Deterministic fixture clock for UTC-normalization tests. */
        $clock = new class implements ClockInterface {
            /**
             * Now according to the AnonymousClass contract.
             *
             * @return DateTimeImmutable Result described by the native PHP return type.
             */
            #[Override]
            public function now() : DateTimeImmutable
            {
                return new DateTimeImmutable(datetime: '2026-10-08 12:15:00', timezone: new DateTimeZone(timezone: 'Europe/Belgrade'));
            }
        };

        $this->log = new ActivityLog(writer: new RecordActivity(pdo: $this->pdo), clock: $clock);
    }
}
