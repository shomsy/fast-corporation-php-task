<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Downloads;

use InterviewTask\Accounts\Accounts;
use InterviewTask\Accounts\Register\Register;
use InterviewTask\Accounts\RequireSignedIn;
use InterviewTask\Accounts\SessionIdentity;
use InterviewTask\ActivityTracking\ActivityLog;
use InterviewTask\Bootstrap\Http\DownloadApi;
use InterviewTask\Downloads\DownloadFile;
use InterviewTask\Infrastructure\Database\Migrator;
use InterviewTask\Infrastructure\Database\SqliteConnection;
use InterviewTask\Infrastructure\Session\PhpSession;
use InterviewTask\Infrastructure\Time\SystemClock;
use Override;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * Verify native binary delivery and accepted audit outcomes over the SPA-only API.
 *
 * @package InterviewTask\Tests\Downloads
 */
final class DownloadApiTest extends TestCase
{
    /**
     * Isolated database for owned ActivityTracking audit.
     *
     * @var PDO
     */
    private PDO $pdo;
    /**
     * Server-owned signed-in identity for the test.
     *
     * @var SessionIdentity
     */
    private SessionIdentity $identity;
    /**
     * Session lifecycle shared across API invocations.
     *
     * @var PhpSession
     */
    private PhpSession $session;

    /**
     * Verify native binary delivery, HTTP headers and one accepted audit event.
     *
     * @return void No return value.
     */
    public function testBinaryAttachmentHasCorrectHeadersAndOneAcceptedAudit() : void
    {
        $api = $this->api(artifact: dirname(path: __DIR__, levels: 2) . '/assets/sample.exe');
        $response = $api->download();
        self::assertSame(200, $response->status());
        self::assertSame(1024, strlen(string: $response->body()));
        self::assertSame('MZ', substr(string: $response->body(), offset: 0, length: 2));
        self::assertSame('application/octet-stream', $response->headers()['Content-Type']);
        self::assertSame('attachment; filename="sample.exe"', $response->headers()['Content-Disposition']);
        self::assertSame('no-store', $response->headers()['Cache-Control']);

        $statement = $this->pdo->query(query: "SELECT action, target FROM activity_events ORDER BY id");
        self::assertNotFalse($statement);
        self::assertSame([['action' => 'button-click', 'target' => 'download']], $statement->fetchAll(mode: PDO::FETCH_ASSOC));
    }

    /**
     * Missing server-owned binary must return 503 without a successful click event.
     *
     * @return void No return value.
     */
    public function testMissingArtifactReturns503AndCannotBeCounted() : void
    {
        $response = $this->api(artifact: '/absent/not-a-real-file.exe')->download();
        self::assertSame(503, $response->status());
        self::assertSame('Download is temporarily unavailable.', $response->body());
        $rows = $this->pdo->query(query: "SELECT COUNT(*) FROM activity_events WHERE target = 'download'");
        self::assertNotFalse($rows);
        self::assertSame(0, (int) $rows->fetchColumn());
    }

    /**
     * Do not misclassify a storage failure as a missing downloadable file.
     *
     * @return void No return value.
     */
    public function testAuditFailureDoesNotGetMisreportedAsArtifactUnavailable() : void
    {
        $this->pdo->exec(statement: 'DROP TABLE activity_events');
        $this->expectException(PDOException::class);
        $this->api(artifact: dirname(path: __DIR__, levels: 2) . '/assets/sample.exe')->download();
    }

    /**
     * Construct the binary API with the real fixed-file owner and signed-in identity.
     *
     * @param string $artifact Trusted test artifact location.
     *
     * @return DownloadApi Authenticated native binary HTTP adapter.
     */
    private function api(string $artifact) : DownloadApi
    {
        return new DownloadApi(
            downloads: new DownloadFile(activityLog: new ActivityLog(pdo: $this->pdo, clock: new SystemClock()), artifactPath: $artifact),
            signedIn: new RequireSignedIn(identity: $this->identity),
        );
    }

    /**
     * Prepare an isolated SQLite database and a signed-in account.
     *
     * @return void No return value.
     */
    #[Override]
    protected function setUp() : void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
        session_id(id: '');
        ini_set(option: 'session.use_cookies', value: '0');

        $this->pdo = SqliteConnection::memory();
        (new Migrator(pdo: $this->pdo, migrationDirectories: [
            'Accounts' => dirname(path: __DIR__, levels: 2) . '/src/Accounts/migrations',
            'ActivityTracking' => dirname(path: __DIR__, levels: 2) . '/src/ActivityTracking/migrations',
        ]))->migrate();
        $this->session = new PhpSession(name: 'download_api_test', secureCookie: false);
        $clock = new SystemClock();
        $accounts = new Accounts(pdo: $this->pdo);
        $this->identity = new SessionIdentity(session: $this->session, accounts: $accounts, clock: $clock);
        $account = (new Register(accounts: $accounts, clock: $clock))->register(username: 'downloaduser', password: 'strong-password');
        $this->identity->signIn(account: $account);
    }

    /**
     * Revoke and clear test session state after each scenario.
     *
     * @return void No return value.
     */
    #[Override]
    protected function tearDown() : void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
        session_id(id: '');
    }
}
