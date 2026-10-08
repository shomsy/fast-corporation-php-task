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

    public function testFixedReadableArtifactIsTrackedAndReturned() : void
    {
        $path = tempnam(sys_get_temp_dir(), 'fast-download-');
        self::assertNotFalse($path);

        try {
            self::assertNotFalse(file_put_contents($path, 'harmless fixed artifact'));
            $download = new DownloadFile(new ActivityLog($this->pdo, new SystemClock()), $path);
            $download->view(9, 'bob');
            self::assertSame('harmless fixed artifact', $download->read(9, 'bob'));

            $statement = $this->pdo->query('SELECT action, target FROM activity_events ORDER BY id');
            self::assertNotFalse($statement);
            self::assertSame([
                                 ['action' => 'view-page', 'target' => 'page-b'],
                                 ['action' => 'button-click', 'target' => 'download'],
                             ], $statement->fetchAll(PDO::FETCH_ASSOC));
        } finally {
            unlink($path);
        }
    }

    public function testActivityDatabaseFailureDoesNotBecomeArtifactUnavailable() : void
    {
        $path = tempnam(sys_get_temp_dir(), 'fast-download-');
        self::assertNotFalse($path);

        try {
            self::assertNotFalse(file_put_contents($path, 'valid artifact'));
            $this->pdo->exec('DROP TABLE activity_events');
            $download = new DownloadFile(new ActivityLog($this->pdo, new SystemClock()), $path);

            $this->expectException(PDOException::class);
            $download->read(9, 'bob');
        } finally {
            unlink($path);
        }
    }

    public function testMissingArtifactDoesNotClaimSuccessfulDownload() : void
    {
        $path     = sys_get_temp_dir() . '/fast-task-absent-artifact-does-not-exist.exe';
        $download = new DownloadFile(new ActivityLog($this->pdo, new SystemClock()), $path);
        $download->view(9, 'bob');

        try {
            $download->read(9, 'bob');
            self::fail('Missing artifact must not be downloadable.');
        } catch (ArtifactUnavailable $exception) {
            self::assertSame('Download is temporarily unavailable.', $exception->getMessage());
        }

        $statement = $this->pdo->query("SELECT COUNT(*) FROM activity_events WHERE action = 'button-click'");
        self::assertNotFalse($statement);
        self::assertSame(0, (int) $statement->fetchColumn());
    }

    protected function setUp() : void
    {
        $this->pdo = SqliteConnection::memory();
        (new Migrator($this->pdo, [
            'ActivityTracking' => dirname(__DIR__, 2) . '/src/ActivityTracking/migrations',
        ]))->migrate();
    }
}
