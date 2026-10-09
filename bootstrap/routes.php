<?php

declare(strict_types=1);

/**
 * Canonical HTTP routes for all requested user and administrator workflows.
 *
 * A central route guard enforces authorization and CSRF; existing adapter checks add defense in depth.
 *
 * @package InterviewTask\Bootstrap
 */

use InterviewTask\Accounts\AccountLifecycle;
use InterviewTask\Accounts\Accounts;
use InterviewTask\Accounts\CurrentAccount;
use InterviewTask\Accounts\Login\Login;
use InterviewTask\Accounts\Register\Register;
use InterviewTask\Accounts\Role;
use InterviewTask\Accounts\RequireAdmin;
use InterviewTask\Accounts\RequireSignedIn;
use InterviewTask\Accounts\SessionIdentity;
use InterviewTask\ActivityTracking\ActivityLog;
use InterviewTask\ActivityTracking\DailyReports;
use InterviewTask\ActivityTracking\Statistics;
use InterviewTask\Bootstrap\Http\AccountPages;
use InterviewTask\Bootstrap\Http\SessionApi;
use InterviewTask\Bootstrap\Http\ActivityPages;
use InterviewTask\Bootstrap\Http\CowOfferPages;
use InterviewTask\Bootstrap\Http\DownloadPages;
use InterviewTask\Bootstrap\Http\HtmlPage;
use InterviewTask\CowOffer\CowOffer;
use InterviewTask\Downloads\DownloadFile;
use InterviewTask\Infrastructure\Database\SqliteConnection;
use InterviewTask\Infrastructure\Database\Transaction;
use InterviewTask\Infrastructure\Diagnostics\SecurityEvents;
use InterviewTask\Infrastructure\Session\Csrf;
use InterviewTask\Infrastructure\Session\PhpSession;
use InterviewTask\Infrastructure\Templating\Renderer;
use InterviewTask\Infrastructure\Time\SystemClock;
use InterviewTask\Runtime\Http\Request;
use InterviewTask\Runtime\Http\RouteAccess;
use InterviewTask\Runtime\Http\Response;
use InterviewTask\Runtime\Http\Router;

/** @var array{databasePath: string, sessionCookieSecure: bool, sessionStoragePath: string|null, root: string, environment: string} $config */
$config = require __DIR__ . '/config.php';

return static function (Router $router) use ($config): void {
    if ($config['environment'] === 'production'
        && ! in_array(needle: $_SERVER['HTTPS'] ?? null, haystack: ['on', '1'], strict: true)) {
        throw new RuntimeException(message: 'Production requests require trusted HTTPS.');
    }

    $pdo = SqliteConnection::open(path: $config['databasePath']);

    // Runtime may only use a database explicitly prepared by the trusted CLI migrator.
    foreach (['schema_migrations', 'accounts', 'activity_events'] as $table) {
        $pdo->query(query: 'SELECT 1 FROM ' . $table . ' LIMIT 1');
    }

    $migrated = $pdo->query(query: 'SELECT COUNT(*) FROM schema_migrations');

    if ($migrated === false || (int) $migrated->fetchColumn() < 3) {
        throw new RuntimeException(message: 'Database migrations have not been applied.');
    }
    $accounts = new Accounts(pdo: $pdo);
    $session  = new PhpSession(secureCookie: $config['sessionCookieSecure'], savePath: $config['sessionStoragePath']);
    $clock    = new SystemClock();
    $identity = new SessionIdentity(session: $session, accounts: $accounts, clock: $clock);
    $current  = new CurrentAccount(identity: $identity);
    $csrf     = new Csrf(session: $session);
    $html     = new HtmlPage(renderer: new Renderer());
    $activity = new ActivityLog(pdo: $pdo, clock: $clock);
    $signedIn = new RequireSignedIn(identity: $identity);

    // Authorization and CSRF are enforced before all route handlers, including future ones.
    $router->guardWith(static function (Request $request, RouteAccess $access) use ($current, $csrf): ?Response {
        if ($access !== RouteAccess::Public) {
            $account = $current->current();

            if ($account === null) {
                return str_starts_with(haystack: $request->path(), needle: '/api/')
                    ? Response::json(payload: ['error' => ['code' => 'unauthenticated', 'message' => 'Sign in required.']], status: 401)
                    : Response::redirect(location: '/login');
            }

            if ($access === RouteAccess::Admin && $account->role !== Role::Admin) {
                $denied = str_starts_with(haystack: $request->path(), needle: '/api/')
                    ? Response::json(payload: ['error' => ['code' => 'forbidden', 'message' => 'Forbidden.']], status: 403)
                    : Response::text(body: 'Forbidden', status: 403);

                return $denied->withHeader(name: 'X-Request-ID', value: SecurityEvents::record(event: 'security.admin_denied'));
            }
        }

        if (! in_array(needle: $request->method(), haystack: ['GET', 'HEAD', 'OPTIONS'], strict: true)
            && ! $csrf->matches(submitted: $request->csrfToken())) {
            $denied = str_starts_with(haystack: $request->path(), needle: '/api/')
                ? Response::json(payload: ['error' => ['code' => 'csrf_denied', 'message' => 'Session verification failed.']], status: 403)
                : Response::text(body: 'Forbidden', status: 403);

            return $denied->withHeader(name: 'X-Request-ID', value: SecurityEvents::record(event: 'security.csrf_denied'));
        }

        return null;
    });

    $lifecycle     = new AccountLifecycle(
        register: new Register(accounts: $accounts, clock: $clock),
        login: new Login(accounts: $accounts, identity: $identity),
        identity: $identity,
        activity: $activity,
        transaction: new Transaction(pdo: $pdo),
    );
    $accountPages  = new AccountPages(lifecycle: $lifecycle, identity: $current, csrf: $csrf, html: $html);
    $sessionApi    = new SessionApi(identity: $current, csrf: $csrf);
    $cowPages      = new CowOfferPages(cow: new CowOffer(activityLog: $activity), signedIn: $signedIn, csrf: $csrf, html: $html);
    $downloadPages = new DownloadPages(
        downloads: new DownloadFile(
            activityLog: $activity,
            artifactPath: $config['root'] . '/assets/sample.exe',
            expectedSha256: '31a97b0f71277adbac9bfde08c935c3e97b0bfa61d59c807c0a900660080cb80',
        ),
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
        session: $session,
    );

    // Fixed, allowlisted SPA assets: no untrusted request path is ever used as a filesystem path.
    $spaFile = static function (string $relativePath, string $contentType) use ($config): Response {
        $body = file_get_contents(filename: $config['root'] . '/public/' . $relativePath);

        if ($body === false) {
            throw new RuntimeException(message: 'Required SPA asset is missing.');
        }

        return new Response(body: $body, headers: ['Content-Type' => $contentType]);
    };

    $router->add(method: 'GET', path: '/app', handler: static fn(): Response => $spaFile('app/index.html', 'text/html; charset=UTF-8'), access: RouteAccess::Public);
    foreach (['/app/login', '/app/register', '/app/home', '/app/page-a', '/app/page-b', '/app/statistics', '/app/reports'] as $screenPath) {
        $router->add(method: 'GET', path: $screenPath, handler: static fn(): Response => $spaFile('app/index.html', 'text/html; charset=UTF-8'), access: RouteAccess::Public);
    }
    $router->add(method: 'GET', path: '/app/startApp.js', handler: static fn(): Response => $spaFile('app/startApp.js', 'text/javascript; charset=UTF-8'), access: RouteAccess::Public);
    $router->add(method: 'GET', path: '/app/navigate.js', handler: static fn(): Response => $spaFile('app/navigate.js', 'text/javascript; charset=UTF-8'), access: RouteAccess::Public);
    $router->add(method: 'GET', path: '/app/requestJson.js', handler: static fn(): Response => $spaFile('app/requestJson.js', 'text/javascript; charset=UTF-8'), access: RouteAccess::Public);
    $router->add(method: 'GET', path: '/assets/app.css', handler: static fn(): Response => $spaFile('assets/app.css', 'text/css; charset=UTF-8'), access: RouteAccess::Public);

    $router->add(method: 'GET', path: '/', handler: static fn(): Response => $accountPages->home(), access: RouteAccess::SignedIn);
    $router->add(method: 'GET', path: '/api/session', handler: static fn(): Response => $sessionApi->session(), access: RouteAccess::Public);
    $router->add(method: 'GET', path: '/api/csrf', handler: static fn(): Response => $sessionApi->csrf(), access: RouteAccess::Public);
    $router->add(method: 'GET', path: '/register', handler: static fn(): Response => $accountPages->registerForm(), access: RouteAccess::Public);
    $router->add(method: 'POST', path: '/register', handler: static fn(Request $request): Response => $accountPages->registerSubmit(request: $request), access: RouteAccess::Public);
    $router->add(method: 'GET', path: '/login', handler: static fn(Request $request): Response => $accountPages->loginForm(request: $request), access: RouteAccess::Public);
    $router->add(method: 'POST', path: '/login', handler: static fn(Request $request): Response => $accountPages->loginSubmit(request: $request), access: RouteAccess::Public);
    $router->add(method: 'POST', path: '/logout', handler: static fn(Request $request): Response => $accountPages->logoutSubmit(request: $request), access: RouteAccess::SignedIn);
    $router->add(method: 'GET', path: '/page-a', handler: static fn(Request $request): Response => $cowPages->pageA(request: $request), access: RouteAccess::SignedIn);
    $router->add(method: 'POST', path: '/buy-cow', handler: static fn(Request $request): Response => $cowPages->buyCow(request: $request), access: RouteAccess::SignedIn);
    $router->add(method: 'GET', path: '/page-b', handler: static fn(Request $request): Response => $downloadPages->pageB(request: $request), access: RouteAccess::SignedIn);
    $router->add(method: 'POST', path: '/download', handler: static fn(Request $request): Response => $downloadPages->download(request: $request), access: RouteAccess::SignedIn);
    $router->add(method: 'GET', path: '/statistics', handler: static fn(Request $request): Response => $activityPages->statisticsPage(request: $request), access: RouteAccess::Admin);
    $router->add(method: 'GET', path: '/reports', handler: static fn(Request $request): Response => $activityPages->reportsPage(request: $request), access: RouteAccess::Admin);
};
