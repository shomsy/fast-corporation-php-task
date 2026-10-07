<?php

declare(strict_types=1);

use InterviewTask\Runtime\Http\Request;
use InterviewTask\Runtime\Http\Router;

/** @var Router $router */
$router = require dirname(__DIR__) . '/bootstrap/app.php';

$router->dispatch(Request::fromGlobals())->send();
