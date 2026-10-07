<?php

declare(strict_types=1);

use InterviewTask\Runtime\Http\Router;

require dirname(__DIR__) . '/vendor/autoload.php';

$router = new Router();

/** @var Closure(Router): void $registerRoutes */
$registerRoutes = require __DIR__ . '/routes.php';
$registerRoutes($router);

return $router;
