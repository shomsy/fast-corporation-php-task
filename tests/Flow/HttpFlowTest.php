<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Flow;

use InterviewTask\Accounts\AccountLifecycle;
use InterviewTask\Accounts\Accounts;
use InterviewTask\Accounts\CreateAdmin\CreateAdmin;
use InterviewTask\Accounts\CurrentAccount;
use InterviewTask\Accounts\Login\Login;
use InterviewTask\Accounts\Register\Register;
use InterviewTask\Accounts\RequireAdmin;
use InterviewTask\Accounts\RequireSignedIn;
use InterviewTask\Accounts\SessionIdentity;
use InterviewTask\ActivityTracking\ActivityLog;
use InterviewTask\ActivityTracking\DailyReports;
use InterviewTask\ActivityTracking\Statistics;
use InterviewTask\ActivityTracking\TrackedPage;
use InterviewTask\Bootstrap\Http\AccountPages;
use InterviewTask\Bootstrap\Http\ActivityPages;
use InterviewTask\Bootstrap\Http\CowOfferPages;
use InterviewTask\Bootstrap\Http\DownloadPages;
use InterviewTask\Bootstrap\Http\HtmlPage;
use InterviewTask\CowOffer\CowOffer;
use InterviewTask\Downloads\DownloadFile;
use InterviewTask\Infrastructure\Database\Migrator;
use InterviewTask\Infrastructure\Database\SqliteConnection;
use InterviewTask\Infrastructure\Database\Transaction;
use InterviewTask\Infrastructure\Session\Csrf;
use InterviewTask\Infrastructure\Session\PhpSession;
use InterviewTask\Infrastructure\Templating\Renderer;
use InterviewTask\Infrastructure\Time\SystemClock;
use InterviewTask\Runtime\Http\Request;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

final class HttpFlowTest extends TestCase
{
    private PDO $pdo;
    private Accounts $accounts;
    private SessionIdentity $identity;
    private Csrf $csrf;
    private AccountPages $accountPages;
    private CowOfferPages $cowPages;
    private DownloadPages $downloadPages;
    private ActivityPages $activityPages;
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
        $activity = new ActivityLog($this->pdo, $this->clock);
        $html = new HtmlPage(new Renderer());
        $signedIn = new RequireSignedIn($this->identity);
        $lifecycle = new AccountLifecycle(
            new Register($this->accounts, $this->clock),
            new Login($this->accounts, $this->identity),
            $this->identity,
            $activity,
            new Transaction($this->pdo),
        );
        $this->accountPages = new AccountPages($lifecycle, new CurrentAccount($this->identity), $this->csrf, $html);
        $this->cowPages = new CowOfferPages(
            new CowOffer($activity),
            $signedIn,
            $this->csrf,
            $html,
        );
        $this->downloadPages = $this->downloadPages(dirname(__DIR__, 2) . '/assets/sample.exe');
        $this->activityPages = new ActivityPages(
            new Statistics($this->pdo),
            new DailyReports($this->pdo),
            new RequireAdmin($this->identity),
            new CurrentAccount($this->identity),
            $html,
        );
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
        self::assertSame(200, $this->accountPages->registerForm()->status());
        self::assertSame(200, $this->accountPages->loginForm(new Request('GET', '/login'))->status());

        $registered = $this->accountPages->registerSubmit(new Request('POST', '/register', post: [
            'username' => 'alice', 'password' => 'safe-pass', 'csrf_token' => $this->csrf->token(),
            'role' => 'admin',
        ]));
        self::assertSame(303, $registered->status());
        self::assertSame('/login?registered=1', $registered->headers()['Location']);
        self::assertNull($this->identity->current());

        $role = $this->pdo->query('SELECT role FROM accounts LIMIT 1');
        self::assertNotFalse($role);
        self::assertSame('user', $role->fetchColumn());

        $bad = $this->accountPages->loginSubmit(new Request('POST', '/login', post: [
            'username' => 'alice', 'password' => 'wrong', 'csrf_token' => $this->csrf->token(),
        ]));
        self::assertSame(401, $bad->status());

        $loggedIn = $this->accountPages->loginSubmit(new Request('POST', '/login', post: [
            'username' => 'alice', 'password' => 'safe-pass', 'csrf_token' => $this->csrf->token(),
        ]));
        self::assertSame(303, $loggedIn->status());
        self::assertSame(200, $this->accountPages->home()->status());

        self::assertSame(200, $this->cowPages->pageA()->status());
        $bought = $this->cowPages->buyCow(new Request('POST', '/buy-cow', post: ['csrf_token' => $this->csrf->token()]));
        self::assertSame(200, $bought->status());
        self::assertStringContainsString('thankYou', $bought->body());
        self::assertStringNotContainsString('>Buy a cow</button>', $bought->body());

        self::assertSame(200, $this->downloadPages->pageB()->status());
        $download = $this->downloadPages->download(new Request('POST', '/download', post: ['csrf_token' => $this->csrf->token()]));
        self::assertSame(200, $download->status());
        self::assertSame('application/octet-stream', $download->headers()['Content-Type']);
        self::assertStringContainsString('sample.exe', $download->headers()['Content-Disposition']);
        self::assertSame('MZ', substr($download->body(), 0, 2));
        self::assertSame("PE\0\0", substr($download->body(), 0x80, 4));

        $loggedOut = $this->accountPages->logoutSubmit(new Request('POST', '/logout', post: ['csrf_token' => $this->csrf->token()]));
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

    public function testStatisticsNextLinkOnlyAppearsWhenAnotherEventExists(): void
    {
        $admin = (new CreateAdmin($this->accounts, $this->clock))->create('admin', 'safe-pass');
        $this->identity->signIn($admin);

        $insert = $this->pdo->prepare(
            "INSERT INTO activity_events (actor_id, actor_label, action, target, occurred_at) VALUES (1, 'admin', 'view-page', 'page-a', '2026-10-08T12:00:00+00:00')",
        );
        self::assertNotFalse($insert);
        for ($i = 0; $i < Statistics::PAGE_SIZE; ++$i) {
            self::assertTrue($insert->execute());
        }

        $exact = $this->activityPages->statisticsPage(new Request('GET', '/statistics'));
        self::assertSame(200, $exact->status());
        self::assertStringNotContainsString('Next →', $exact->body());

        self::assertTrue($insert->execute());
        $next = $this->activityPages->statisticsPage(new Request('GET', '/statistics'));
        self::assertStringContainsString('Next →', $next->body());
        $last = $this->activityPages->statisticsPage(new Request('GET', '/statistics', query: ['page' => '2']));
        self::assertStringNotContainsString('Next →', $last->body());
        self::assertStringContainsString('← Previous', $last->body());

        $oldPage = $this->activityPages->statisticsPage(new Request('GET', '/statistics', query: ['page' => '1001']));
        self::assertSame(200, $oldPage->status());
        self::assertStringNotContainsString('Next →', $oldPage->body());
    }

    public function testAnonymousAndRegularUsersCannotReadAdminPages(): void
    {
        self::assertSame('/login', $this->cowPages->pageA()->headers()['Location']);
        self::assertSame('/login', $this->downloadPages->pageB()->headers()['Location']);
        self::assertSame('/login', $this->activityPages->statisticsPage(new Request('GET', '/statistics'))->headers()['Location']);
        self::assertSame('/login', $this->activityPages->reportsPage()->headers()['Location']);

        $account = (new Register($this->accounts, $this->clock))->register('alice', 'safe-pass');
        $this->identity->signIn($account);
        self::assertSame(403, $this->activityPages->statisticsPage(new Request('GET', '/statistics'))->status());
        self::assertSame(403, $this->activityPages->reportsPage()->status());
    }

    public function testCsrfRejectsEveryStateChangingRouteAndDoesNotWriteEvents(): void
    {
        $account = (new Register($this->accounts, $this->clock))->register('alice', 'safe-pass');
        $this->identity->signIn($account);
        foreach ([
            $this->accountPages->registerSubmit(new Request('POST', '/register')),
            $this->accountPages->loginSubmit(new Request('POST', '/login')),
            $this->cowPages->buyCow(new Request('POST', '/buy-cow')),
            $this->downloadPages->download(new Request('POST', '/download')),
            $this->accountPages->logoutSubmit(new Request('POST', '/logout')),
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
        $this->cowPages->pageA();
        $this->downloadPages->pageB();
        $this->cowPages->buyCow(new Request('POST', '/buy-cow', post: ['csrf_token' => $this->csrf->token()]));
        $this->downloadPages->download(new Request('POST', '/download', post: ['csrf_token' => $this->csrf->token()]));

        (new ActivityLog($this->pdo, $this->clock))->recordPageView(3, '<script>alert(1)</script>', TrackedPage::PageA);
        $stats = $this->activityPages->statisticsPage(new Request('GET', '/statistics', query: ['action' => 'view-page']));
        self::assertSame(200, $stats->status());
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $stats->body());
        self::assertStringNotContainsString('<script>alert(1)</script>', $stats->body());

        $empty = $this->activityPages->statisticsPage(new Request('GET', '/statistics', query: ['user' => 'missing']));
        self::assertSame(200, $empty->status());
        self::assertStringContainsString('No matching activity events', $empty->body());

        $invalid = $this->activityPages->statisticsPage(new Request('GET', '/statistics', query: ['date' => '2026-02-31']));
        self::assertSame(422, $invalid->status());

        $report = $this->activityPages->reportsPage();
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
        $pages = $this->downloadPages(dirname(__DIR__, 2) . '/assets/does-not-exist.exe');
        $response = $pages->download(new Request('POST', '/download', post: ['csrf_token' => $this->csrf->token()]));
        self::assertSame(503, $response->status());
        $statement = $this->pdo->query("SELECT COUNT(*) FROM activity_events WHERE target = 'download'");
        self::assertNotFalse($statement);
        self::assertSame(0, (int) $statement->fetchColumn());
    }

    public function testDownloadAuditFailurePropagatesDatabaseException(): void
    {
        $account = (new Register($this->accounts, $this->clock))->register('alice', 'safe-pass');
        $this->identity->signIn($account);
        $token = $this->csrf->token();
        $this->pdo->exec('DROP TABLE activity_events');

        $this->expectException(PDOException::class);
        $this->downloadPages->download(new Request('POST', '/download', post: [
            'csrf_token' => $token,
        ]));
    }

    public function testMissingDownloadArtifactKeeps503EvenIfAuditDatabaseIsUnavailable(): void
    {
        $account = (new Register($this->accounts, $this->clock))->register('alice', 'safe-pass');
        $this->identity->signIn($account);
        $token = $this->csrf->token();
        $this->pdo->exec('DROP TABLE activity_events');

        $pages = $this->downloadPages(dirname(__DIR__, 2) . '/assets/does-not-exist.exe');
        $response = $pages->download(new Request('POST', '/download', post: ['csrf_token' => $token]));
        self::assertSame(503, $response->status());
    }

    public function testRegistrationTrackingFailureRollsBackAccountCreation(): void
    {
        $this->pdo->exec('DROP TABLE activity_events');

        $this->expectException(PDOException::class);
        try {
            $this->accountPages->registerSubmit(new Request('POST', '/register', post: [
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
            $this->accountPages->logoutSubmit(new Request('POST', '/logout', post: ['csrf_token' => $token]));
            self::fail('Activity failure must be visible.');
        } catch (PDOException) {
            self::assertNull($this->identity->current());
        }
    }

    public function testHtmlResponsesCarryNoStoreAndBrowserSecurityHeaders(): void
    {
        $response = $this->accountPages->registerForm();

        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertSame('nosniff', $response->headers()['X-Content-Type-Options']);
        self::assertSame('DENY', $response->headers()['X-Frame-Options']);
        self::assertStringContainsString('frame-ancestors', $response->headers()['Content-Security-Policy']);
        self::assertSame('no-referrer', $response->headers()['Referrer-Policy']);
    }

    public function testRegistrationRejectsCaseInsensitiveDuplicateWithoutAdditionalEvent(): void
    {
        $first = $this->accountPages->registerSubmit(new Request('POST', '/register', post: [
            'username' => 'Alice', 'password' => 'safe-pass', 'csrf_token' => $this->csrf->token(),
        ]));
        self::assertSame(303, $first->status());

        $duplicate = $this->accountPages->registerSubmit(new Request('POST', '/register', post: [
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
            $this->accountPages->loginSubmit(new Request('POST', '/login', post: [
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

        $response = $this->downloadPages->download(new Request('POST', '/download', post: [
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
        $buy = $this->cowPages->buyCow(new Request('POST', '/buy-cow', post: [
            'csrf_token' => $csrf,
        ]));
        $download = $this->downloadPages->download(new Request('POST', '/download', post: [
            'csrf_token' => $csrf,
        ]));
        self::assertSame('/login', $buy->headers()['Location']);
        self::assertSame('/login', $download->headers()['Location']);

        $statement = $this->pdo->query('SELECT COUNT(*) FROM activity_events');
        self::assertNotFalse($statement);
        self::assertSame(0, (int) $statement->fetchColumn());
    }

    public function testExpiredAuthenticationCannotTriggerProtectedViewsOrActions(): void
    {
        $account = (new Register($this->accounts, $this->clock))->register('alice', 'safe-pass');
        $this->identity->signIn($account);
        $csrf = $this->csrf->token();
        $_SESSION['last_activity_at'] = time() - 1801;

        self::assertSame('/login', $this->cowPages->pageA()->headers()['Location']);
        self::assertSame('/login', $this->downloadPages->pageB()->headers()['Location']);
        self::assertSame('/login', $this->cowPages->buyCow(new Request('POST', '/buy-cow', post: [
            'csrf_token' => $csrf,
        ]))->headers()['Location']);
        self::assertSame('/login', $this->downloadPages->download(new Request('POST', '/download', post: [
            'csrf_token' => $csrf,
        ]))->headers()['Location']);
        self::assertNull($this->identity->current());

        $statement = $this->pdo->query('SELECT COUNT(*) FROM activity_events');
        self::assertNotFalse($statement);
        self::assertSame(0, (int) $statement->fetchColumn());
    }

    private function downloadPages(string $artifact): DownloadPages
    {
        return new DownloadPages(
            new DownloadFile(new ActivityLog($this->pdo, $this->clock), $artifact),
            new RequireSignedIn($this->identity),
            $this->csrf,
            new HtmlPage(new Renderer()),
        );
    }
}
