<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Flow;

use InterviewTask\Accounts\Accounts;
use InterviewTask\Accounts\CreateAdmin\CreateAdmin;
use InterviewTask\Accounts\Register\Register;
use InterviewTask\Accounts\SessionIdentity;
use InterviewTask\ActivityTracking\ActivityLog;
use InterviewTask\ActivityTracking\TrackedPage;
use InterviewTask\Bootstrap\WebPages;
use InterviewTask\Infrastructure\Database\Migrator;
use InterviewTask\Infrastructure\Database\SqliteConnection;
use InterviewTask\Infrastructure\Session\Csrf;
use InterviewTask\Infrastructure\Session\PhpSession;
use InterviewTask\Infrastructure\Templating\Renderer;
use InterviewTask\Infrastructure\Time\SystemClock;
use InterviewTask\Runtime\Http\Request;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/bootstrap/WebPages.php';

final class WebPagesTest extends TestCase
{
    private PDO $pdo;
    private Accounts $accounts;
    private SessionIdentity $identity;
    private Csrf $csrf;
    private WebPages $pages;
    private SystemClock $clock;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
        session_id('');
        ini_set('session.use_cookies', '0');

        $this->pdo = SqliteConnection::memory();
        (new Migrator($this->pdo, [
            'Accounts' => dirname(__DIR__, 2) . '/src/Accounts/migrations',
            'ActivityTracking' => dirname(__DIR__, 2) . '/src/ActivityTracking/migrations',
        ]))->migrate();

        $this->accounts = new Accounts($this->pdo);
        $session = new PhpSession('web_test', false);
        $this->identity = new SessionIdentity($session, $this->accounts);
        $this->csrf = new Csrf($session);
        $this->clock = new SystemClock();
        $this->pages = $this->webPages(dirname(__DIR__, 2) . '/assets/sample.exe');
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
        session_id('');
    }

    public function testEndToEndBrowserFlowRecordsEveryRequiredUserEvent(): void
    {
        self::assertSame(200, $this->pages->registerForm()->status());
        self::assertSame(200, $this->pages->loginForm(new Request('GET', '/login'))->status());

        $registered = $this->pages->registerSubmit(new Request('POST', '/register', post: [
            'username' => 'alice', 'password' => 'safe-pass', 'csrf_token' => $this->csrf->token(),
            'role' => 'admin',
        ]));
        self::assertSame(303, $registered->status());
        self::assertSame('/login?registered=1', $registered->headers()['Location']);
        self::assertNull($this->identity->current());

        $role = $this->pdo->query('SELECT role FROM accounts LIMIT 1');
        self::assertNotFalse($role);
        self::assertSame('user', $role->fetchColumn());

        $bad = $this->pages->loginSubmit(new Request('POST', '/login', post: [
            'username' => 'alice', 'password' => 'wrong', 'csrf_token' => $this->csrf->token(),
        ]));
        self::assertSame(401, $bad->status());

        $loggedIn = $this->pages->loginSubmit(new Request('POST', '/login', post: [
            'username' => 'alice', 'password' => 'safe-pass', 'csrf_token' => $this->csrf->token(),
        ]));
        self::assertSame(303, $loggedIn->status());
        self::assertSame(200, $this->pages->home()->status());

        self::assertSame(200, $this->pages->pageA()->status());
        $bought = $this->pages->buyCow(new Request('POST', '/buy-cow', post: ['csrf_token' => $this->csrf->token()]));
        self::assertSame(200, $bought->status());
        self::assertStringContainsString('thankYou', $bought->body());
        self::assertStringNotContainsString('>Buy a cow</button>', $bought->body());

        self::assertSame(200, $this->pages->pageB()->status());
        $download = $this->pages->download(new Request('POST', '/download', post: ['csrf_token' => $this->csrf->token()]));
        self::assertSame(200, $download->status());
        self::assertSame('application/octet-stream', $download->headers()['Content-Type']);
        self::assertStringContainsString('sample.exe', $download->headers()['Content-Disposition']);
        self::assertSame('MZ', substr($download->body(), 0, 2));
        self::assertSame("PE\0\0", substr($download->body(), 0x80, 4));

        $loggedOut = $this->pages->logoutSubmit(new Request('POST', '/logout', post: ['csrf_token' => $this->csrf->token()]));
        self::assertSame(303, $loggedOut->status());
        self::assertNull($this->identity->current());

        $statement = $this->pdo->query('SELECT action, target FROM activity_events ORDER BY id');
        self::assertNotFalse($statement);
        self::assertSame([
            ['action' => 'registration', 'target' => null],
            ['action' => 'login', 'target' => null],
            ['action' => 'view-page', 'target' => 'page-a'],
            ['action' => 'button-click', 'target' => 'buy-cow'],
            ['action' => 'view-page', 'target' => 'page-b'],
            ['action' => 'button-click', 'target' => 'download'],
            ['action' => 'logout', 'target' => null],
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function testAnonymousAndRegularUsersCannotReadAdminPages(): void
    {
        self::assertSame('/login', $this->pages->pageA()->headers()['Location']);
        self::assertSame('/login', $this->pages->pageB()->headers()['Location']);
        self::assertSame('/login', $this->pages->statisticsPage(new Request('GET', '/statistics'))->headers()['Location']);
        self::assertSame('/login', $this->pages->reportsPage()->headers()['Location']);

        $account = (new Register($this->accounts, $this->clock))->register('alice', 'safe-pass');
        $this->identity->signIn($account);
        self::assertSame(403, $this->pages->statisticsPage(new Request('GET', '/statistics'))->status());
        self::assertSame(403, $this->pages->reportsPage()->status());
    }

    public function testCsrfRejectsEveryStateChangingRouteAndDoesNotWriteEvents(): void
    {
        $account = (new Register($this->accounts, $this->clock))->register('alice', 'safe-pass');
        $this->identity->signIn($account);
        foreach ([
            $this->pages->registerSubmit(new Request('POST', '/register')),
            $this->pages->loginSubmit(new Request('POST', '/login')),
            $this->pages->buyCow(new Request('POST', '/buy-cow')),
            $this->pages->download(new Request('POST', '/download')),
            $this->pages->logoutSubmit(new Request('POST', '/logout')),
        ] as $response) {
            self::assertSame(403, $response->status());
        }
        self::assertNotNull($this->identity->current());
        $statement = $this->pdo->query('SELECT COUNT(*) FROM activity_events');
        self::assertNotFalse($statement);
        self::assertSame(0, (int) $statement->fetchColumn());
    }

    public function testAdminStatisticsAndReportsShowCorrectDataAndEscapeUntrustedLabels(): void
    {
        $admin = (new CreateAdmin($this->accounts, $this->clock))->create('root', 'safe-pass');
        $this->identity->signIn($admin);
        $this->pages->pageA();
        $this->pages->pageB();
        $this->pages->buyCow(new Request('POST', '/buy-cow', post: ['csrf_token' => $this->csrf->token()]));
        $this->pages->download(new Request('POST', '/download', post: ['csrf_token' => $this->csrf->token()]));

        (new ActivityLog($this->pdo, $this->clock))->recordPageView(3, '<script>alert(1)</script>', TrackedPage::PageA);
        $stats = $this->pages->statisticsPage(new Request('GET', '/statistics', query: ['action' => 'view-page']));
        self::assertSame(200, $stats->status());
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $stats->body());
        self::assertStringNotContainsString('<script>alert(1)</script>', $stats->body());

        $empty = $this->pages->statisticsPage(new Request('GET', '/statistics', query: ['user' => 'missing']));
        self::assertSame(200, $empty->status());
        self::assertStringContainsString('No matching activity events', $empty->body());

        $invalid = $this->pages->statisticsPage(new Request('GET', '/statistics', query: ['date' => '2026-02-31']));
        self::assertSame(422, $invalid->status());

        $report = $this->pages->reportsPage();
        self::assertSame(200, $report->status());
        self::assertStringContainsString('<svg', $report->body());
        self::assertStringContainsString('<polyline', $report->body());
        self::assertStringContainsString('Page A views', $report->body());
        self::assertStringContainsString('Buy a cow clicks', $report->body());
        self::assertStringContainsString('Download clicks', $report->body());
        self::assertStringContainsString('Daily totals', $report->body());
    }

    public function testMissingArtifactFailsWithoutDownloadActivity(): void
    {
        $account = (new Register($this->accounts, $this->clock))->register('alice', 'safe-pass');
        $this->identity->signIn($account);
        $pages = $this->webPages(dirname(__DIR__, 2) . '/assets/does-not-exist.exe');
        $response = $pages->download(new Request('POST', '/download', post: ['csrf_token' => $this->csrf->token()]));
        self::assertSame(503, $response->status());
        $statement = $this->pdo->query("SELECT COUNT(*) FROM activity_events WHERE target = 'download'");
        self::assertNotFalse($statement);
        self::assertSame(0, (int) $statement->fetchColumn());
    }

    public function testRegistrationTrackingFailureRollsBackAccountCreation(): void
    {
        $this->pdo->exec('DROP TABLE activity_events');

        $this->expectException(PDOException::class);
        try {
            $this->pages->registerSubmit(new Request('POST', '/register', post: [
                'username' => 'alice', 'password' => 'safe-pass', 'csrf_token' => $this->csrf->token(),
            ]));
        } finally {
            $statement = $this->pdo->query('SELECT COUNT(*) FROM accounts');
            self::assertNotFalse($statement);
            self::assertSame(0, (int) $statement->fetchColumn());
        }
    }

    public function testLogoutAlwaysRevokesIdentityWhenActivityWriteFails(): void
    {
        $account = (new Register($this->accounts, $this->clock))->register('alice', 'safe-pass');
        $this->identity->signIn($account);
        $token = $this->csrf->token();
        $this->pdo->exec('DROP TABLE activity_events');

        try {
            $this->pages->logoutSubmit(new Request('POST', '/logout', post: ['csrf_token' => $token]));
            self::fail('Activity failure must be visible.');
        } catch (PDOException) {
            self::assertNull($this->identity->current());
        }
    }

    public function testHtmlResponsesCarryNoStoreAndBrowserSecurityHeaders(): void
    {
        $response = $this->pages->registerForm();

        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertSame('nosniff', $response->headers()['X-Content-Type-Options']);
        self::assertSame('DENY', $response->headers()['X-Frame-Options']);
        self::assertStringContainsString('frame-ancestors', $response->headers()['Content-Security-Policy']);
        self::assertSame('no-referrer', $response->headers()['Referrer-Policy']);
    }

    public function testRegistrationRejectsCaseInsensitiveDuplicateWithoutAdditionalEvent(): void
    {
        $first = $this->pages->registerSubmit(new Request('POST', '/register', post: [
            'username' => 'Alice', 'password' => 'safe-pass', 'csrf_token' => $this->csrf->token(),
        ]));
        self::assertSame(303, $first->status());

        $duplicate = $this->pages->registerSubmit(new Request('POST', '/register', post: [
            'username' => 'alice', 'password' => 'safe-pass', 'csrf_token' => $this->csrf->token(),
        ]));
        self::assertSame(409, $duplicate->status());

        $statement = $this->pdo->query('SELECT COUNT(*) FROM accounts');
        self::assertNotFalse($statement);
        self::assertSame(1, (int) $statement->fetchColumn());
        $statement = $this->pdo->query("SELECT COUNT(*) FROM activity_events WHERE action = 'registration'");
        self::assertNotFalse($statement);
        self::assertSame(1, (int) $statement->fetchColumn());
    }

    public function testLoginTrackingFailureRevokesSessionAndPropagatesFailure(): void
    {
        (new Register($this->accounts, $this->clock))->register('alice', 'safe-pass');
        $this->pdo->exec('DROP TABLE activity_events');

        try {
            $this->pages->loginSubmit(new Request('POST', '/login', post: [
                'username' => 'alice', 'password' => 'safe-pass',
                'csrf_token' => $this->csrf->token(),
            ]));
            self::fail('Successful login cannot silently omit its required activity event.');
        } catch (PDOException) {
            self::assertNull($this->identity->current());
        }
    }

    public function testDownloadNeverUsesUserSuppliedFilePath(): void
    {
        $account = (new Register($this->accounts, $this->clock))->register('alice', 'safe-pass');
        $this->identity->signIn($account);

        $response = $this->pages->download(new Request('POST', '/download', post: [
            'csrf_token' => $this->csrf->token(),
            'file' => '../../../../etc/passwd',
        ]));
        self::assertSame(200, $response->status());
        self::assertSame('MZ', substr($response->body(), 0, 2));
        self::assertSame('no-store', $response->headers()['Cache-Control']);
    }

    public function testAnonymousCannotTriggerSignedInActionsEvenWithValidCsrfToken(): void
    {
        $csrf = $this->csrf->token();
        $buy = $this->pages->buyCow(new Request('POST', '/buy-cow', post: [
            'csrf_token' => $csrf,
        ]));
        $download = $this->pages->download(new Request('POST', '/download', post: [
            'csrf_token' => $csrf,
        ]));
        self::assertSame('/login', $buy->headers()['Location']);
        self::assertSame('/login', $download->headers()['Location']);

        $statement = $this->pdo->query('SELECT COUNT(*) FROM activity_events');
        self::assertNotFalse($statement);
        self::assertSame(0, (int) $statement->fetchColumn());
    }

    private function webPages(string $artifact): WebPages
    {
        return new WebPages(
            $this->pdo,
            $this->accounts,
            $this->identity,
            $this->csrf,
            new Renderer(),
            $this->clock,
            $artifact,
        );
    }
}
