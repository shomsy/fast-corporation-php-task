<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Downloads;

use InterviewTask\ActivityTracking\ActivityLog;
use InterviewTask\Downloads\ArtifactUnavailable;
use InterviewTask\Downloads\DownloadFile;
use InterviewTask\Infrastructure\Database\Migrator;
use InterviewTask\Infrastructure\Database\SqliteConnection;
use InterviewTask\Infrastructure\Time\SystemClock;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

final class DownloadFileTest extends TestCase
{
    private PDO $pdo;

    public function testFixedReadableArtifactIsTrackedAndReturned(): void
    {
        $path = tempnam(directory: sys_get_temp_dir(), prefix: 'fast-download-');
        self::assertNotFalse($path);

        try {
            self::assertNotFalse(file_put_contents(filename: $path, data: 'harmless fixed artifact'));
            $download = new DownloadFile(activityLog: new ActivityLog(pdo: $this->pdo, clock: new SystemClock()), artifactPath: $path);
            $download->view(actorId: 9, actorLabel: 'bob');
            self::assertSame('harmless fixed artifact', $download->read(actorId: 9, actorLabel: 'bob'));

            $statement = $this->pdo->query(query: 'SELECT action, target FROM activity_events ORDER BY id');
            self::assertNotFalse($statement);
            self::assertSame([
                ['action' => 'view-page', 'target' => 'page-b'],
                ['action' => 'button-click', 'target' => 'download'],
            ], $statement->fetchAll(mode: PDO::FETCH_ASSOC));
        } finally {
            unlink(filename: $path);
        }
    }

    public function testActivityDatabaseFailureDoesNotBecomeArtifactUnavailable(): void
    {
        $path = tempnam(directory: sys_get_temp_dir(), prefix: 'fast-download-');
        self::assertNotFalse($path);

        try {
            self::assertNotFalse(file_put_contents(filename: $path, data: 'valid artifact'));
            $this->pdo->exec(statement: 'DROP TABLE activity_events');
            $download = new DownloadFile(activityLog: new ActivityLog(pdo: $this->pdo, clock: new SystemClock()), artifactPath: $path);

            $this->expectException(PDOException::class);
            $download->read(actorId: 9, actorLabel: 'bob');
        } finally {
            unlink(filename: $path);
        }
    }

    public function testMissingArtifactDoesNotClaimSuccessfulDownload(): void
    {
        $path     = sys_get_temp_dir() . '/fast-task-absent-artifact-does-not-exist.exe';
        $download = new DownloadFile(activityLog: new ActivityLog(pdo: $this->pdo, clock: new SystemClock()), artifactPath: $path);
        $download->view(actorId: 9, actorLabel: 'bob');

        try {
            $download->read(actorId: 9, actorLabel: 'bob');
            self::fail('Missing artifact must not be downloadable.');
        } catch (ArtifactUnavailable $exception) {
            self::assertSame('Download is temporarily unavailable.', $exception->getMessage());
        }

        $statement = $this->pdo->query(query: "SELECT COUNT(*) FROM activity_events WHERE action = 'button-click'");
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
    }
}
