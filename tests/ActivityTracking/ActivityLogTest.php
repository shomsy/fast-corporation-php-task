<?php

declare(strict_types=1);

namespace InterviewTask\Tests\ActivityTracking;

use DateTimeImmutable;
use DateTimeZone;
use InterviewTask\ActivityTracking\ActivityAction;
use InterviewTask\ActivityTracking\ActivityLog;
use InterviewTask\ActivityTracking\TrackedButton;
use InterviewTask\ActivityTracking\TrackedPage;
use InterviewTask\Infrastructure\Database\Migrator;
use InterviewTask\Infrastructure\Database\SqliteConnection;
use InterviewTask\Infrastructure\Database\Transaction;
use InvalidArgumentException;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

final class ActivityLogTest extends TestCase
{
    private PDO         $pdo;
    private ActivityLog $log;

    public function testRecordsAllRequiredEventsWithHistoricalActorAndUtcTimestamp(): void
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

    public function testDoesNotReadAccountsOrChangeEarlierActorLabels(): void
    {
        $this->log->recordLogin(actorId: 14, actorLabel: 'old-label');
        $this->log->recordLogout(actorId: 14, actorLabel: 'new-label');

        $statement = $this->pdo->query(query: 'SELECT actor_label FROM activity_events ORDER BY id');
        self::assertNotFalse($statement);
        self::assertSame(['old-label', 'new-label'], $statement->fetchAll(mode: PDO::FETCH_COLUMN));
    }

    public function testRejectsInvalidActorBeforeWriting(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->log->recordLogin(actorId: 0, actorLabel: 'alice');
    }

    public function testRejectsBlankActorLabelBeforeWriting(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->log->recordLogin(actorId: 1, actorLabel: '  ');
    }

    public function testDatabaseRejectsInvalidEventTaxonomy(): void
    {
        $this->expectException(PDOException::class);
        $this->pdo->exec(statement: "INSERT INTO activity_events (actor_id, actor_label, action, target, occurred_at) VALUES (1, 'alice', 'button-click', 'page-a', '2026-10-08T10:15:00+00:00')");
    }

    public function testParticipatesInSameLocalTransactionAndRollsBack(): void
    {
        try {
            (new Transaction(pdo: $this->pdo))->run(operation: function (): void {
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

    #[\Override]

    protected function setUp(): void
    {
        $this->pdo = SqliteConnection::memory();

        (new Migrator(pdo: $this->pdo, migrationDirectories: [
            'ActivityTracking' => dirname(path: __DIR__, levels: 2) . '/src/ActivityTracking/migrations',
        ]))->migrate();

        $clock = new class implements ClockInterface {
            #[\Override]
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable(datetime: '2026-10-08 12:15:00', timezone: new DateTimeZone(timezone: 'Europe/Belgrade'));
            }
        };

        $this->log = new ActivityLog(pdo: $this->pdo, clock: $clock);
    }
}
