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
use Override;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * Regression checks for Http flow test, covering observable behavior and failure paths.
 *
 * @package InterviewTask\Tests\Flow
 */
final class HttpFlowTest extends TestCase
{
    /**
     * Internal state or dependency owned by HttpFlowTest.
     *
     * @var PDO
     */
    private PDO             $pdo;
    /**
     * Internal state or dependency owned by HttpFlowTest.
     *
     * @var Accounts
     */
    private Accounts        $accounts;
    /**
     * Internal state or dependency owned by HttpFlowTest.
     *
     * @var SessionIdentity
     */
    private SessionIdentity $identity;
    /**
     * Internal state or dependency owned by HttpFlowTest.
     *
     * @var Csrf
     */
    private Csrf            $csrf;
    /**
     * Internal state or dependency owned by HttpFlowTest.
     *
     * @var AccountPages
     */
    private AccountPages    $accountPages;
    /**
     * Internal state or dependency owned by HttpFlowTest.
     *
     * @var CowOfferPages
     */
    private CowOfferPages   $cowPages;
    /**
     * Internal state or dependency owned by HttpFlowTest.
     *
     * @var DownloadPages
     */
    private DownloadPages   $downloadPages;
    /**
     * Internal state or dependency owned by HttpFlowTest.
     *
     * @var ActivityPages
     */
    private ActivityPages   $activityPages;
    /**
     * Internal state or dependency owned by HttpFlowTest.
     *
     * @var SystemClock
     */
    private SystemClock     $clock;

    /**
     * Verify end to end browser flow records every required user event through the public behavior.
     *
     * @return void No return value.
     */
    public function testEndToEndBrowserFlowRecordsEveryRequiredUserEvent(): void
    {
        self::assertSame(200, $this->accountPages->registerForm()->status());
        self::assertSame(200, $this->accountPages->loginForm(request: new Request(method: 'GET', path: '/login'))->status());

        $registered = $this->accountPages->registerSubmit(request: new Request(method: 'POST', path: '/register', post: [
            'username' => 'alice', 'password' => 'safe-pass', 'csrf_token' => $this->csrf->token(),
            'role'     => 'admin',
        ]));
        self::assertSame(303, $registered->status());
        self::assertSame('/login?registered=1', $registered->headers()['Location']);
        self::assertNull($this->identity->current());

        $role = $this->pdo->query(query: 'SELECT role FROM accounts LIMIT 1');
        self::assertNotFalse($role);
        self::assertSame('user', $role->fetchColumn());

        $bad = $this->accountPages->loginSubmit(request: new Request(method: 'POST', path: '/login', post: [
            'username' => 'alice', 'password' => 'wrong', 'csrf_token' => $this->csrf->token(),
        ]));
        self::assertSame(401, $bad->status());

        $loggedIn = $this->accountPages->loginSubmit(request: new Request(method: 'POST', path: '/login', post: [
            'username' => 'alice', 'password' => 'safe-pass', 'csrf_token' => $this->csrf->token(),
        ]));
        self::assertSame(303, $loggedIn->status());
        self::assertSame(200, $this->accountPages->home()->status());

        self::assertSame(200, $this->cowPages->pageA()->status());
        $bought = $this->cowPages->buyCow(request: new Request(method: 'POST', path: '/buy-cow', post: ['csrf_token' => $this->csrf->token()]));
        self::assertSame(200, $bought->status());
        self::assertStringContainsString('thankYou', $bought->body());
        self::assertStringNotContainsString('>Buy a cow</button>', $bought->body());

        self::assertSame(200, $this->downloadPages->pageB()->status());
        $download = $this->downloadPages->download(request: new Request(method: 'POST', path: '/download', post: ['csrf_token' => $this->csrf->token()]));
        self::assertSame(200, $download->status());
        self::assertSame('application/octet-stream', $download->headers()['Content-Type']);
        self::assertStringContainsString('sample.exe', $download->headers()['Content-Disposition']);
        self::assertSame('MZ', substr(string: $download->body(), offset: 0, length: 2));
        self::assertSame("PE\0\0", substr(string: $download->body(), offset: 0x80, length: 4));

        $loggedOut = $this->accountPages->logoutSubmit(request: new Request(method: 'POST', path: '/logout', post: ['csrf_token' => $this->csrf->token()]));
        self::assertSame(303, $loggedOut->status());
        self::assertNull($this->identity->current());

        $statement = $this->pdo->query(query: 'SELECT action, target FROM activity_events ORDER BY id');
        self::assertNotFalse($statement);
        self::assertSame([
            ['action' => 'registration', 'target' => null],
            ['action' => 'login', 'target' => null],
            ['action' => 'view-page', 'target' => 'page-a'],
            ['action' => 'button-click', 'target' => 'buy-cow'],
            ['action' => 'view-page', 'target' => 'page-b'],
            ['action' => 'button-click', 'target' => 'download'],
            ['action' => 'logout', 'target' => null],
        ], $statement->fetchAll(mode: PDO::FETCH_ASSOC));
    }

    /**
     * Verify statistics next link only appears when another event exists through the public behavior.
     *
     * @return void No return value.
     */
    public function testStatisticsNextLinkOnlyAppearsWhenAnotherEventExists(): void
    {
        $admin = (new CreateAdmin(accounts: $this->accounts, clock: $this->clock))->create(username: 'admin', password: 'safe-pass');
        $this->identity->signIn(account: $admin);

        $insert = $this->pdo->prepare(
            query: "INSERT INTO activity_events (actor_id, actor_label, action, target, occurred_at) VALUES (1, 'admin', 'view-page', 'page-a', '2026-10-08T12:00:00+00:00')",
        );
        self::assertNotFalse($insert);
        for ($i = 0; $i < Statistics::PAGE_SIZE; ++$i) {
            self::assertTrue($insert->execute());
        }

        $exact = $this->activityPages->statisticsPage(request: new Request(method: 'GET', path: '/statistics'));
        self::assertSame(200, $exact->status());
        self::assertStringNotContainsString('Next →', $exact->body());

        self::assertTrue($insert->execute());
        $next = $this->activityPages->statisticsPage(request: new Request(method: 'GET', path: '/statistics'));
        self::assertStringContainsString('Next →', $next->body());
        $last = $this->activityPages->statisticsPage(request: new Request(method: 'GET', path: '/statistics', query: ['page' => '2']));
        self::assertStringNotContainsString('Next →', $last->body());
        self::assertStringContainsString('← Previous', $last->body());

        $oldPage = $this->activityPages->statisticsPage(request: new Request(method: 'GET', path: '/statistics', query: ['page' => '1001']));
        self::assertSame(200, $oldPage->status());
        self::assertStringNotContainsString('Next →', $oldPage->body());
    }

    /**
     * Verify anonymous and regular users cannot read admin pages through the public behavior.
     *
     * @return void No return value.
     */
    public function testAnonymousAndRegularUsersCannotReadAdminPages(): void
    {
        self::assertSame('/login', $this->cowPages->pageA()->headers()['Location']);
        self::assertSame('/login', $this->downloadPages->pageB()->headers()['Location']);
        self::assertSame('/login', $this->activityPages->statisticsPage(request: new Request(method: 'GET', path: '/statistics'))->headers()['Location']);
        self::assertSame('/login', $this->activityPages->reportsPage()->headers()['Location']);

        $account = (new Register(accounts: $this->accounts, clock: $this->clock))->register(username: 'alice', password: 'safe-pass');
        $this->identity->signIn(account: $account);
        self::assertSame(403, $this->activityPages->statisticsPage(request: new Request(method: 'GET', path: '/statistics'))->status());
        self::assertSame(403, $this->activityPages->reportsPage()->status());
    }

    /**
     * Verify csrf rejects every state changing route and does not write events through the public behavior.
     *
     * @return void No return value.
     */
    public function testCsrfRejectsEveryStateChangingRouteAndDoesNotWriteEvents(): void
    {
        $account = (new Register(accounts: $this->accounts, clock: $this->clock))->register(username: 'alice', password: 'safe-pass');
        $this->identity->signIn(account: $account);
        foreach ([
            $this->accountPages->registerSubmit(request: new Request(method: 'POST', path: '/register')),
            $this->accountPages->loginSubmit(request: new Request(method: 'POST', path: '/login')),
            $this->cowPages->buyCow(request: new Request(method: 'POST', path: '/buy-cow')),
            $this->downloadPages->download(request: new Request(method: 'POST', path: '/download')),
            $this->accountPages->logoutSubmit(request: new Request(method: 'POST', path: '/logout')),
        ] as $response) {
            self::assertSame(403, $response->status());
        }
        self::assertNotNull($this->identity->current());
        $statement = $this->pdo->query(query: 'SELECT COUNT(*) FROM activity_events');
        self::assertNotFalse($statement);
        self::assertSame(0, (int) $statement->fetchColumn());
    }

    /**
     * Verify admin statistics and reports show correct data and escape untrusted labels through the public behavior.
     *
     * @return void No return value.
     */
    public function testAdminStatisticsAndReportsShowCorrectDataAndEscapeUntrustedLabels(): void
    {
        $admin = (new CreateAdmin(accounts: $this->accounts, clock: $this->clock))->create(username: 'root', password: 'safe-pass');
        $this->identity->signIn(account: $admin);
        $this->cowPages->pageA();
        $this->downloadPages->pageB();
        $this->cowPages->buyCow(request: new Request(method: 'POST', path: '/buy-cow', post: ['csrf_token' => $this->csrf->token()]));
        $this->downloadPages->download(request: new Request(method: 'POST', path: '/download', post: ['csrf_token' => $this->csrf->token()]));

        (new ActivityLog(pdo: $this->pdo, clock: $this->clock))->recordPageView(actorId: 3, actorLabel: '<script>alert(1)</script>', page: TrackedPage::PageA);
        $stats = $this->activityPages->statisticsPage(request: new Request(method: 'GET', path: '/statistics', query: ['action' => 'view-page']));
        self::assertSame(200, $stats->status());
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $stats->body());
        self::assertStringNotContainsString('<script>alert(1)</script>', $stats->body());

        $empty = $this->activityPages->statisticsPage(request: new Request(method: 'GET', path: '/statistics', query: ['user' => 'missing']));
        self::assertSame(200, $empty->status());
        self::assertStringContainsString('No matching activity events', $empty->body());

        $invalid = $this->activityPages->statisticsPage(request: new Request(method: 'GET', path: '/statistics', query: ['date' => '2026-02-31']));
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

    /**
     * Verify missing artifact fails without download activity through the public behavior.
     *
     * @return void No return value.
     */
    public function testMissingArtifactFailsWithoutDownloadActivity(): void
    {
        $account = (new Register(accounts: $this->accounts, clock: $this->clock))->register(username: 'alice', password: 'safe-pass');
        $this->identity->signIn(account: $account);
        $pages    = $this->downloadPages(artifact: dirname(path: __DIR__, levels: 2) . '/assets/does-not-exist.exe');
        $response = $pages->download(request: new Request(method: 'POST', path: '/download', post: ['csrf_token' => $this->csrf->token()]));
        self::assertSame(503, $response->status());
        $statement = $this->pdo->query(query: "SELECT COUNT(*) FROM activity_events WHERE target = 'download'");
        self::assertNotFalse($statement);
        self::assertSame(0, (int) $statement->fetchColumn());
    }

    /**
     * Download pages according to the HttpFlowTest contract.
     *
     * @param string $artifact Input value for artifact.
     * @return DownloadPages Result described by the native PHP return type.
     */
    private function downloadPages(string $artifact): DownloadPages
    {
        return new DownloadPages(
            downloads: new DownloadFile(activityLog: new ActivityLog(pdo: $this->pdo, clock: $this->clock), artifactPath: $artifact),
            signedIn: new RequireSignedIn(identity: $this->identity),
            csrf: $this->csrf,
            html: new HtmlPage(renderer: new Renderer()),
        );
    }

    /**
     * Verify download audit failure propagates database exception through the public behavior.
     *
     * @return void No return value.
     */
    public function testDownloadAuditFailurePropagatesDatabaseException(): void
    {
        $account = (new Register(accounts: $this->accounts, clock: $this->clock))->register(username: 'alice', password: 'safe-pass');
        $this->identity->signIn(account: $account);
        $token = $this->csrf->token();
        $this->pdo->exec(statement: 'DROP TABLE activity_events');

        $this->expectException(PDOException::class);
        $this->downloadPages->download(request: new Request(method: 'POST', path: '/download', post: [
            'csrf_token' => $token,
        ]));
    }

    /**
     * Verify missing download artifact keeps503 even if audit database is unavailable through the public behavior.
     *
     * @return void No return value.
     */
    public function testMissingDownloadArtifactKeeps503EvenIfAuditDatabaseIsUnavailable(): void
    {
        $account = (new Register(accounts: $this->accounts, clock: $this->clock))->register(username: 'alice', password: 'safe-pass');
        $this->identity->signIn(account: $account);
        $token = $this->csrf->token();
        $this->pdo->exec(statement: 'DROP TABLE activity_events');

        $pages    = $this->downloadPages(artifact: dirname(path: __DIR__, levels: 2) . '/assets/does-not-exist.exe');
        $response = $pages->download(request: new Request(method: 'POST', path: '/download', post: ['csrf_token' => $token]));
        self::assertSame(503, $response->status());
    }

    /**
     * Verify registration tracking failure rolls back account creation through the public behavior.
     *
     * @return void No return value.
     */
    public function testRegistrationTrackingFailureRollsBackAccountCreation(): void
    {
        $this->pdo->exec(statement: 'DROP TABLE activity_events');

        $this->expectException(PDOException::class);
        try {
            $this->accountPages->registerSubmit(request: new Request(method: 'POST', path: '/register', post: [
                'username' => 'alice', 'password' => 'safe-pass', 'csrf_token' => $this->csrf->token(),
            ]));
        } finally {
            $statement = $this->pdo->query(query: 'SELECT COUNT(*) FROM accounts');
            self::assertNotFalse($statement);
            self::assertSame(0, (int) $statement->fetchColumn());
        }
    }

    /**
     * Verify logout revokes session even when account lookup fails through the public behavior.
     *
     * @return void No return value.
     */
    public function testLogoutRevokesSessionEvenWhenAccountLookupFails(): void
    {
        $account = (new Register(accounts: $this->accounts, clock: $this->clock))->register(username: 'alice', password: 'safe-pass');
        $this->identity->signIn(account: $account);
        $token = $this->csrf->token();
        $this->pdo->exec(statement: 'DROP TABLE accounts');

        try {
            $this->accountPages->logoutSubmit(request: new Request(method: 'POST', path: '/logout', post: [
                'csrf_token' => $token,
            ]));
            self::fail('Account lookup failure must remain visible to the HTTP error boundary.');
        } catch (PDOException) {
            self::assertSame([], $_SESSION);
            self::assertNotSame(PHP_SESSION_ACTIVE, session_status());
        }
    }

    /**
     * Verify logout always revokes identity when activity write fails through the public behavior.
     *
     * @return void No return value.
     */
    public function testLogoutAlwaysRevokesIdentityWhenActivityWriteFails(): void
    {
        $account = (new Register(accounts: $this->accounts, clock: $this->clock))->register(username: 'alice', password: 'safe-pass');
        $this->identity->signIn(account: $account);
        $token = $this->csrf->token();
        $this->pdo->exec(statement: 'DROP TABLE activity_events');

        try {
            $this->accountPages->logoutSubmit(request: new Request(method: 'POST', path: '/logout', post: ['csrf_token' => $token]));
            self::fail('Activity failure must be visible.');
        } catch (PDOException) {
            self::assertNull($this->identity->current());
        }
    }

    /**
     * Verify html responses carry no store and browser security headers through the public behavior.
     *
     * @return void No return value.
     */
    public function testHtmlResponsesCarryNoStoreAndBrowserSecurityHeaders(): void
    {
        $response = $this->accountPages->registerForm();

        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertSame('nosniff', $response->headers()['X-Content-Type-Options']);
        self::assertSame('DENY', $response->headers()['X-Frame-Options']);
        self::assertStringContainsString('frame-ancestors', $response->headers()['Content-Security-Policy']);
        self::assertSame('no-referrer', $response->headers()['Referrer-Policy']);
    }

    /**
     * Verify registration rejects case insensitive duplicate without additional event through the public behavior.
     *
     * @return void No return value.
     */
    public function testRegistrationRejectsCaseInsensitiveDuplicateWithoutAdditionalEvent(): void
    {
        $first = $this->accountPages->registerSubmit(request: new Request(method: 'POST', path: '/register', post: [
            'username' => 'Alice', 'password' => 'safe-pass', 'csrf_token' => $this->csrf->token(),
        ]));
        self::assertSame(303, $first->status());

        $duplicate = $this->accountPages->registerSubmit(request: new Request(method: 'POST', path: '/register', post: [
            'username' => 'alice', 'password' => 'safe-pass', 'csrf_token' => $this->csrf->token(),
        ]));
        self::assertSame(409, $duplicate->status());

        $statement = $this->pdo->query(query: 'SELECT COUNT(*) FROM accounts');
        self::assertNotFalse($statement);
        self::assertSame(1, (int) $statement->fetchColumn());
        $statement = $this->pdo->query(query: "SELECT COUNT(*) FROM activity_events WHERE action = 'registration'");
        self::assertNotFalse($statement);
        self::assertSame(1, (int) $statement->fetchColumn());
    }

    /**
     * Verify login tracking failure revokes session and propagates failure through the public behavior.
     *
     * @return void No return value.
     */
    public function testLoginTrackingFailureRevokesSessionAndPropagatesFailure(): void
    {
        (new Register(accounts: $this->accounts, clock: $this->clock))->register(username: 'alice', password: 'safe-pass');
        $this->pdo->exec(statement: 'DROP TABLE activity_events');

        try {
            $this->accountPages->loginSubmit(request: new Request(method: 'POST', path: '/login', post: [
                'username'   => 'alice', 'password' => 'safe-pass',
                'csrf_token' => $this->csrf->token(),
            ]));
            self::fail('Successful login cannot silently omit its required activity event.');
        } catch (PDOException) {
            self::assertNull($this->identity->current());
        }
    }

    /**
     * Verify download never uses user supplied file path through the public behavior.
     *
     * @return void No return value.
     */
    public function testDownloadNeverUsesUserSuppliedFilePath(): void
    {
        $account = (new Register(accounts: $this->accounts, clock: $this->clock))->register(username: 'alice', password: 'safe-pass');
        $this->identity->signIn(account: $account);

        $response = $this->downloadPages->download(request: new Request(method: 'POST', path: '/download', post: [
            'csrf_token' => $this->csrf->token(),
            'file'       => '../../../../etc/passwd',
        ]));
        self::assertSame(200, $response->status());
        self::assertSame('MZ', substr(string: $response->body(), offset: 0, length: 2));
        self::assertSame('no-store', $response->headers()['Cache-Control']);
    }

    /**
     * Verify anonymous cannot trigger signed in actions even with valid csrf token through the public behavior.
     *
     * @return void No return value.
     */
    public function testAnonymousCannotTriggerSignedInActionsEvenWithValidCsrfToken(): void
    {
        $csrf     = $this->csrf->token();
        $buy      = $this->cowPages->buyCow(request: new Request(method: 'POST', path: '/buy-cow', post: [
            'csrf_token' => $csrf,
        ]));
        $download = $this->downloadPages->download(request: new Request(method: 'POST', path: '/download', post: [
            'csrf_token' => $csrf,
        ]));
        self::assertSame('/login', $buy->headers()['Location']);
        self::assertSame('/login', $download->headers()['Location']);

        $statement = $this->pdo->query(query: 'SELECT COUNT(*) FROM activity_events');
        self::assertNotFalse($statement);
        self::assertSame(0, (int) $statement->fetchColumn());
    }

    /**
     * Verify expired authentication cannot trigger protected views or actions through the public behavior.
     *
     * @return void No return value.
     */
    public function testExpiredAuthenticationCannotTriggerProtectedViewsOrActions(): void
    {
        $account = (new Register(accounts: $this->accounts, clock: $this->clock))->register(username: 'alice', password: 'safe-pass');
        $this->identity->signIn(account: $account);
        $csrf                         = $this->csrf->token();
        $_SESSION['last_activity_at'] = time() - 1801;

        self::assertSame('/login', $this->cowPages->pageA()->headers()['Location']);
        self::assertSame('/login', $this->downloadPages->pageB()->headers()['Location']);
        self::assertSame('/login', $this->cowPages->buyCow(request: new Request(method: 'POST', path: '/buy-cow', post: [
            'csrf_token' => $csrf,
        ]))->headers()['Location']);
        self::assertSame('/login', $this->downloadPages->download(request: new Request(method: 'POST', path: '/download', post: [
            'csrf_token' => $csrf,
        ]))->headers()['Location']);
        self::assertNull($this->identity->current());

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

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
        session_id(id: '');
        ini_set(option: 'session.use_cookies', value: '0');

        $this->pdo = SqliteConnection::memory();
        (new Migrator(pdo: $this->pdo, migrationDirectories: [
            'Accounts'         => dirname(path: __DIR__, levels: 2) . '/src/Accounts/migrations',
            'ActivityTracking' => dirname(path: __DIR__, levels: 2) . '/src/ActivityTracking/migrations',
        ]))->migrate();

        $this->accounts      = new Accounts(pdo: $this->pdo);
        $session             = new PhpSession(name: 'web_test', secureCookie: false);
        $this->identity      = new SessionIdentity(session: $session, accounts: $this->accounts);
        $this->csrf          = new Csrf(session: $session);
        $this->clock         = new SystemClock();
        $activity            = new ActivityLog(pdo: $this->pdo, clock: $this->clock);
        $html                = new HtmlPage(renderer: new Renderer());
        $signedIn            = new RequireSignedIn(identity: $this->identity);
        $lifecycle           = new AccountLifecycle(
            register: new Register(accounts: $this->accounts, clock: $this->clock),
            login: new Login(accounts: $this->accounts, identity: $this->identity),
            identity: $this->identity,
            activity: $activity,
            transaction: new Transaction(pdo: $this->pdo),
        );
        $this->accountPages  = new AccountPages(lifecycle: $lifecycle, identity: new CurrentAccount(identity: $this->identity), csrf: $this->csrf, html: $html);
        $this->cowPages      = new CowOfferPages(
            cow: new CowOffer(activityLog: $activity),
            signedIn: $signedIn,
            csrf: $this->csrf,
            html: $html,
        );
        $this->downloadPages = $this->downloadPages(artifact: dirname(path: __DIR__, levels: 2) . '/assets/sample.exe');
        $this->activityPages = new ActivityPages(
            statistics: new Statistics(pdo: $this->pdo),
            reports: new DailyReports(pdo: $this->pdo),
            admin: new RequireAdmin(identity: $this->identity),
            identity: new CurrentAccount(identity: $this->identity),
            html: $html,
        );
    }

    /**
     * Release or reset test resources without leaking state.
     *
     * @return void No return value.
     */
    #[Override]

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
        session_id(id: '');
    }
}
