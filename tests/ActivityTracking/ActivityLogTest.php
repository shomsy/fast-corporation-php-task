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
use InterviewTask\Infrastructure\Time\Clock;
use InvalidArgumentException;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

final class ActivityLogTest extends TestCase
{
    private PDO $pdo;
    private ActivityLog $log;

    protected function setUp(): void
    {
        $this->pdo = SqliteConnection::memory();

        (new Migrator($this->pdo, [
            'ActivityTracking' => dirname(__DIR__, 2) . '/src/ActivityTracking/migrations',
        ]))->migrate();

        $clock = new class implements Clock {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-10-08 12:15:00', new DateTimeZone('Europe/Belgrade'));
            }
        };

        $this->log = new ActivityLog($this->pdo, $clock);
    }

    public function testRecordsAllRequiredEventsWithHistoricalActorAndUtcTimestamp(): void
    {
        $this->log->recordRegistration(7, 'alice');
        $this->log->recordLogin(7, 'alice');
        $this->log->recordPageView(7, 'alice', TrackedPage::PageA);
        $this->log->recordPageView(7, 'alice', TrackedPage::PageB);
        $this->log->recordButtonClick(7, 'alice', TrackedButton::BuyCow);
        $this->log->recordButtonClick(7, 'alice', TrackedButton::Download);
        $this->log->recordLogout(7, 'alice');

        $statement = $this->pdo->query('SELECT actor_id, actor_label, action, target, occurred_at FROM activity_events ORDER BY id');
        self::assertNotFalse($statement);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
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
        $this->log->recordLogin(14, 'old-label');
        $this->log->recordLogout(14, 'new-label');

        $statement = $this->pdo->query('SELECT actor_label FROM activity_events ORDER BY id');
        self::assertNotFalse($statement);
        self::assertSame(['old-label', 'new-label'], $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public function testRejectsInvalidActorBeforeWriting(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->log->recordLogin(0, 'alice');
    }

    public function testRejectsBlankActorLabelBeforeWriting(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->log->recordLogin(1, '  ');
    }

    public function testDatabaseRejectsInvalidEventTaxonomy(): void
    {
        $this->expectException(PDOException::class);
        $this->pdo->exec("INSERT INTO activity_events (actor_id, actor_label, action, target, occurred_at) VALUES (1, 'alice', 'button-click', 'page-a', '2026-10-08T10:15:00+00:00')");
    }

    public function testParticipatesInSameLocalTransactionAndRollsBack(): void
    {
        try {
            (new Transaction($this->pdo))->run(function (): void {
                $this->log->recordRegistration(5, 'alice');
                $this->pdo->exec("INSERT INTO activity_events (actor_id, actor_label, action, target, occurred_at) VALUES (5, 'alice', 'view-page', 'buy-cow', '2026-10-08T10:15:00+00:00')");
            });
        } catch (PDOException) {
            // The second write violates event taxonomy; first write must roll back.
        }
        $statement = $this->pdo->query('SELECT COUNT(*) FROM activity_events');
        self::assertNotFalse($statement);
        self::assertSame(0, (int) $statement->fetchColumn());
    }
}
