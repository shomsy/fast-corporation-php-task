<?php

declare(strict_types=1);

/**
 * Application composition root, connecting business owners to shared infrastructure.
 *
 * Construct the PDO connection, migration-independent runtime services, session security, and HTTP route wiring.
 *
 * @package InterviewTask\Bootstrap
 */

use InterviewTask\Runtime\Http\Router;

require dirname(path: __DIR__) . '/vendor/autoload.php';

$router = new Router();

/** @var Closure(Router): void $registerRoutes */
$registerRoutes = require __DIR__ . '/routes.php';
$registerRoutes($router);

return $router;
