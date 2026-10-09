<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Downloads;

use InterviewTask\ActivityTracking\ActivityLog;
use InterviewTask\Downloads\ArtifactUnavailable;
use InterviewTask\Downloads\DownloadFile;
use InterviewTask\Infrastructure\Database\Migrator;
use InterviewTask\Infrastructure\Database\SqliteConnection;
use InterviewTask\Infrastructure\Time\SystemClock;
use Override;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * Regression checks for Download file test, covering observable behavior and failure paths.
 *
 * @package InterviewTask\Tests\Downloads
 */
final class DownloadFileTest extends TestCase
{
    /**
     * Internal state or dependency owned by DownloadFileTest.
     *
     * @var PDO
     */
    private PDO $pdo;

    /**
     * Verify fixed readable artifact is tracked and returned through the public behavior.
     *
     * @return void No return value.
     */
    public function testFixedReadableArtifactIsTrackedAndReturned() : void
    {
        $path = tempnam(directory: sys_get_temp_dir(), prefix: 'fast-download-');
        self::assertNotFalse($path);

        try {
            self::assertNotFalse(file_put_contents(filename: $path, data: 'harmless fixed artifact'));
            $download = new DownloadFile(activityLog: new ActivityLog(pdo: $this->pdo, clock: new SystemClock()), artifactPath: $path);
            $download->view(actorId: 9, actorLabel: 'bob');
            self::assertSame('harmless fixed artifact', $download->prepareDownload(actorId: 9, actorLabel: 'bob'));

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

    /**
     * Verify activity database failure does not become artifact unavailable through the public behavior.
     *
     * @return void No return value.
     */
    public function testActivityDatabaseFailureDoesNotBecomeArtifactUnavailable() : void
    {
        $path = tempnam(directory: sys_get_temp_dir(), prefix: 'fast-download-');
        self::assertNotFalse($path);

        try {
            self::assertNotFalse(file_put_contents(filename: $path, data: 'valid artifact'));
            $this->pdo->exec(statement: 'DROP TABLE activity_events');
            $download = new DownloadFile(activityLog: new ActivityLog(pdo: $this->pdo, clock: new SystemClock()), artifactPath: $path);

            $this->expectException(PDOException::class);
            $download->prepareDownload(actorId: 9, actorLabel: 'bob');
        } finally {
            unlink(filename: $path);
        }
    }

    /**
     * Verify missing artifact does not claim successful download through the public behavior.
     *
     * @return void No return value.
     */
    public function testMissingArtifactDoesNotClaimSuccessfulDownload() : void
    {
        $path     = sys_get_temp_dir() . '/fast-task-absent-artifact-does-not-exist.exe';
        $download = new DownloadFile(activityLog: new ActivityLog(pdo: $this->pdo, clock: new SystemClock()), artifactPath: $path);
        $download->view(actorId: 9, actorLabel: 'bob');

        try {
            $download->prepareDownload(actorId: 9, actorLabel: 'bob');
            self::fail('Missing artifact must not be downloadable.');
        } catch (ArtifactUnavailable $exception) {
            self::assertSame('Download is temporarily unavailable.', $exception->getMessage());
        }

        $statement = $this->pdo->query(query: "SELECT COUNT(*) FROM activity_events WHERE action = 'button-click'");
        self::assertNotFalse($statement);
        self::assertSame(0, (int) $statement->fetchColumn());
    }

    /**
     * A changed fixed file is unavailable and never counted as a successful download.
     *
     * @return void No return value.
     */
    public function testUnexpectedArtifactHashRejectsDownloadBeforeAudit() : void
    {
        $path = tempnam(directory: sys_get_temp_dir(), prefix: 'fast-hash-');
        self::assertNotFalse($path);
        try {
            self::assertNotFalse(file_put_contents(filename: $path, data: 'unexpected bytes'));
            $download = new DownloadFile(
                activityLog   : new ActivityLog(pdo: $this->pdo, clock: new SystemClock()),
                artifactPath  : $path,
                expectedSha256: hash(algo: 'sha256', data: 'original bytes'),
            );
            $this->expectException(ArtifactUnavailable::class);
            $download->prepareDownload(actorId: 9, actorLabel: 'bob');
        } finally {
            unlink(filename: $path);
            $statement = $this->pdo->query(query: "SELECT COUNT(*) FROM activity_events WHERE action = 'button-click'");
            self::assertNotFalse($statement);
            self::assertSame(0, (int) $statement->fetchColumn());
        }
    }

    /**
     * A matching pinned SHA-256 allows the fixed artifact and records exactly one accepted click.
     *
     * @return void No return value.
     */
    public function testMatchingArtifactHashAllowsDownloadAndOneAuditEvent() : void
    {
        $path = tempnam(directory: sys_get_temp_dir(), prefix: 'fast-hash-valid-');
        self::assertNotFalse($path);

        try {
            $contents = 'known artifact bytes';
            self::assertNotFalse(file_put_contents(filename: $path, data: $contents));
            $download = new DownloadFile(
                activityLog   : new ActivityLog(pdo: $this->pdo, clock: new SystemClock()),
                artifactPath  : $path,
                expectedSha256: hash(algo: 'sha256', data: $contents),
            );
            self::assertSame($contents, $download->prepareDownload(actorId: 9, actorLabel: 'bob'));
            $count = $this->pdo->query(query: "SELECT COUNT(*) FROM activity_events WHERE action = 'button-click' AND target = 'download'");
            self::assertNotFalse($count);
            self::assertSame(1, (int) $count->fetchColumn());
        } finally {
            unlink(filename: $path);
        }
    }

    /**
     * Files beyond the configured size budget fail closed without reading the whole file.
     *
     * @return void No return value.
     */
    public function testOversizedArtifactRejectsDownloadBeforeAudit() : void
    {
        $path = tempnam(directory: sys_get_temp_dir(), prefix: 'fast-big-');
        self::assertNotFalse($path);
        try {
            self::assertNotFalse(file_put_contents(filename: $path, data: str_repeat(string: 'z', times: 4096)));
            $download = new DownloadFile(
                activityLog : new ActivityLog(pdo: $this->pdo, clock: new SystemClock()),
                artifactPath: $path,
                maxBytes    : 1024,
            );
            $this->expectException(ArtifactUnavailable::class);
            $download->prepareDownload(actorId: 9, actorLabel: 'bob');
        } finally {
            unlink(filename: $path);
            $statement = $this->pdo->query(query: "SELECT COUNT(*) FROM activity_events WHERE action = 'button-click'");
            self::assertNotFalse($statement);
            self::assertSame(0, (int) $statement->fetchColumn());
        }
    }

    /**
     * Symlink substitution is never accepted as the fixed downloadable file.
     *
     * @return void No return value.
     */
    public function testSymlinkArtifactCannotBeDownloaded() : void
    {
        $real = tempnam(directory: sys_get_temp_dir(), prefix: 'fast-link-target-');
        self::assertNotFalse($real);
        $link = $real . '.exe';
        try {
            self::assertNotFalse(file_put_contents(filename: $real, data: 'target'));
            self::assertTrue(symlink(target: $real, link: $link));
            $download = new DownloadFile(
                activityLog : new ActivityLog(pdo: $this->pdo, clock: new SystemClock()),
                artifactPath: $link,
            );
            $this->expectException(ArtifactUnavailable::class);
            $download->prepareDownload(actorId: 9, actorLabel: 'bob');
        } finally {
            if (is_link(filename: $link)) {
                unlink(filename: $link);
            }
            unlink(filename: $real);
        }
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
    }
}
