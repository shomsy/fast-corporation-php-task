<?php

declare(strict_types=1);

use InterviewTask\Accounts\Accounts;
use InterviewTask\Accounts\SessionIdentity;
use InterviewTask\Bootstrap\WebPages;
use InterviewTask\Infrastructure\Database\SqliteConnection;
use InterviewTask\Infrastructure\Session\Csrf;
use InterviewTask\Infrastructure\Session\PhpSession;
use InterviewTask\Infrastructure\Templating\Renderer;
use InterviewTask\Infrastructure\Time\SystemClock;
use InterviewTask\Runtime\Http\Request;
use InterviewTask\Runtime\Http\Response;
use InterviewTask\Runtime\Http\Router;

require_once __DIR__ . '/WebPages.php';

/** @var array{databasePath: string, sessionCookieSecure: bool, root: string} $config */
$config = require __DIR__ . '/config.php';

return static function (Router $router) use ($config): void {
    $pdo = SqliteConnection::open($config['databasePath']);
    $accounts = new Accounts($pdo);
    $session = new PhpSession(secureCookie: $config['sessionCookieSecure']);
    $identity = new SessionIdentity($session, $accounts);
    $pages = new WebPages(
        $pdo,
        $accounts,
        $identity,
        new Csrf($session),
        new Renderer(),
        new SystemClock(),
        $config['root'] . '/assets/sample.exe',
    );

    $router->add('GET', '/', static fn(): Response => $pages->home());
    $router->add('GET', '/register', static fn(): Response => $pages->registerForm());
    $router->add('POST', '/register', static fn(Request $request): Response => $pages->registerSubmit($request));
    $router->add('GET', '/login', static fn(Request $request): Response => $pages->loginForm($request));
    $router->add('POST', '/login', static fn(Request $request): Response => $pages->loginSubmit($request));
    $router->add('POST', '/logout', static fn(Request $request): Response => $pages->logoutSubmit($request));
    $router->add('GET', '/page-a', static fn(): Response => $pages->pageA());
    $router->add('POST', '/buy-cow', static fn(Request $request): Response => $pages->buyCow($request));
    $router->add('GET', '/page-b', static fn(): Response => $pages->pageB());
    $router->add('POST', '/download', static fn(Request $request): Response => $pages->download($request));
    $router->add('GET', '/statistics', static fn(Request $request): Response => $pages->statisticsPage($request));
    $router->add('GET', '/reports', static fn(): Response => $pages->reportsPage());
};
