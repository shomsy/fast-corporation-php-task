<?php

declare(strict_types=1);

use InterviewTask\Accounts\AccountLifecycle;
use InterviewTask\Accounts\Accounts;
use InterviewTask\Accounts\CurrentAccount;
use InterviewTask\Accounts\Login\Login;
use InterviewTask\Accounts\Register\Register;
use InterviewTask\Accounts\RequireAdmin;
use InterviewTask\Accounts\RequireSignedIn;
use InterviewTask\Accounts\SessionIdentity;
use InterviewTask\ActivityTracking\ActivityLog;
use InterviewTask\ActivityTracking\DailyReports;
use InterviewTask\ActivityTracking\Statistics;
use InterviewTask\Bootstrap\Http\AccountPages;
use InterviewTask\Bootstrap\Http\ActivityPages;
use InterviewTask\Bootstrap\Http\CowOfferPages;
use InterviewTask\Bootstrap\Http\DownloadPages;
use InterviewTask\Bootstrap\Http\HtmlPage;
use InterviewTask\CowOffer\CowOffer;
use InterviewTask\Downloads\DownloadFile;
use InterviewTask\Infrastructure\Database\SqliteConnection;
use InterviewTask\Infrastructure\Database\Transaction;
use InterviewTask\Infrastructure\Session\Csrf;
use InterviewTask\Infrastructure\Session\PhpSession;
use InterviewTask\Infrastructure\Templating\Renderer;
use InterviewTask\Infrastructure\Time\SystemClock;
use InterviewTask\Runtime\Http\Request;
use InterviewTask\Runtime\Http\Response;
use InterviewTask\Runtime\Http\Router;

/** @var array{databasePath: string, sessionCookieSecure: bool, root: string} $config */
$config = require __DIR__ . '/config.php';

return static function (Router $router) use ($config): void {
    $pdo      = SqliteConnection::open(path: $config['databasePath']);
    $accounts = new Accounts(pdo: $pdo);
    $session  = new PhpSession(secureCookie: $config['sessionCookieSecure']);
    $clock    = new SystemClock();
    $identity = new SessionIdentity(session: $session, accounts: $accounts, clock: $clock);
    $current  = new CurrentAccount(identity: $identity);
    $csrf     = new Csrf(session: $session);
    $html     = new HtmlPage(renderer: new Renderer());
    $activity = new ActivityLog(pdo: $pdo, clock: $clock);
    $signedIn = new RequireSignedIn(identity: $identity);

    $lifecycle     = new AccountLifecycle(
        register: new Register(accounts: $accounts, clock: $clock),
        login: new Login(accounts: $accounts, identity: $identity),
        identity: $identity,
        activity: $activity,
        transaction: new Transaction(pdo: $pdo),
    );
    $accountPages  = new AccountPages(lifecycle: $lifecycle, identity: $current, csrf: $csrf, html: $html);
    $cowPages      = new CowOfferPages(cow: new CowOffer(activityLog: $activity), signedIn: $signedIn, csrf: $csrf, html: $html);
    $downloadPages = new DownloadPages(
        downloads: new DownloadFile(activityLog: $activity, artifactPath: $config['root'] . '/assets/sample.exe'),
        signedIn: $signedIn,
        csrf: $csrf,
        html: $html,
    );
    $activityPages = new ActivityPages(
        statistics: new Statistics(pdo: $pdo),
        reports: new DailyReports(pdo: $pdo),
        admin: new RequireAdmin(identity: $identity),
        identity: $current,
        html: $html,
    );

    $router->add(method: 'GET', path: '/', handler: static fn(): Response => $accountPages->home());
    $router->add(method: 'GET', path: '/register', handler: static fn(): Response => $accountPages->registerForm());
    $router->add(method: 'POST', path: '/register', handler: static fn(Request $request): Response => $accountPages->registerSubmit(request: $request));
    $router->add(method: 'GET', path: '/login', handler: static fn(Request $request): Response => $accountPages->loginForm(request: $request));
    $router->add(method: 'POST', path: '/login', handler: static fn(Request $request): Response => $accountPages->loginSubmit(request: $request));
    $router->add(method: 'POST', path: '/logout', handler: static fn(Request $request): Response => $accountPages->logoutSubmit(request: $request));
    $router->add(method: 'GET', path: '/page-a', handler: static fn(): Response => $cowPages->pageA());
    $router->add(method: 'POST', path: '/buy-cow', handler: static fn(Request $request): Response => $cowPages->buyCow(request: $request));
    $router->add(method: 'GET', path: '/page-b', handler: static fn(): Response => $downloadPages->pageB());
    $router->add(method: 'POST', path: '/download', handler: static fn(Request $request): Response => $downloadPages->download(request: $request));
    $router->add(method: 'GET', path: '/statistics', handler: static fn(Request $request): Response => $activityPages->statisticsPage(request: $request));
    $router->add(method: 'GET', path: '/reports', handler: static fn(Request $request): Response => $activityPages->reportsPage(request: $request));
};
