<?php

declare(strict_types=1);

use InterviewTask\Accounts\AccountLifecycle;
use InterviewTask\Accounts\Accounts;
use InterviewTask\Accounts\Login\Login;
use InterviewTask\Accounts\Logout\Logout;
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
    $pdo = SqliteConnection::open($config['databasePath']);
    $accounts = new Accounts($pdo);
    $session = new PhpSession(secureCookie: $config['sessionCookieSecure']);
    $clock = new SystemClock();
    $identity = new SessionIdentity($session, $accounts, $clock);
    $csrf = new Csrf($session);
    $html = new HtmlPage(new Renderer());
    $activity = new ActivityLog($pdo, $clock);
    $signedIn = new RequireSignedIn($identity);

    $lifecycle = new AccountLifecycle(
        new Register($accounts, $clock),
        new Login($accounts, $identity),
        new Logout($identity),
        $identity,
        $activity,
        new Transaction($pdo),
    );
    $accountPages = new AccountPages($lifecycle, $identity, $csrf, $html);
    $cowPages = new CowOfferPages(new CowOffer($activity), $signedIn, $csrf, $html);
    $downloadPages = new DownloadPages(
        new DownloadFile($activity, $config['root'] . '/assets/sample.exe'),
        $signedIn,
        $csrf,
        $html,
    );
    $activityPages = new ActivityPages(
        new Statistics($pdo),
        new DailyReports($pdo),
        new RequireAdmin($identity),
        $identity,
        $html,
    );

    $router->add('GET', '/', static fn(): Response => $accountPages->home());
    $router->add('GET', '/register', static fn(): Response => $accountPages->registerForm());
    $router->add('POST', '/register', static fn(Request $request): Response => $accountPages->registerSubmit($request));
    $router->add('GET', '/login', static fn(Request $request): Response => $accountPages->loginForm($request));
    $router->add('POST', '/login', static fn(Request $request): Response => $accountPages->loginSubmit($request));
    $router->add('POST', '/logout', static fn(Request $request): Response => $accountPages->logoutSubmit($request));
    $router->add('GET', '/page-a', static fn(): Response => $cowPages->pageA());
    $router->add('POST', '/buy-cow', static fn(Request $request): Response => $cowPages->buyCow($request));
    $router->add('GET', '/page-b', static fn(): Response => $downloadPages->pageB());
    $router->add('POST', '/download', static fn(Request $request): Response => $downloadPages->download($request));
    $router->add('GET', '/statistics', static fn(Request $request): Response => $activityPages->statisticsPage($request));
    $router->add('GET', '/reports', static fn(Request $request): Response => $activityPages->reportsPage($request));
};
