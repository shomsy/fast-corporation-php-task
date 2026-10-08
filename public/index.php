<?php

declare(strict_types=1);

use InterviewTask\Runtime\Http\Request;
use InterviewTask\Runtime\Http\Router;

// Never expose exception messages or stack traces to HTTP clients.
ini_set('display_errors', '0');

try {
    /** @var Router $router */
    $router = require dirname(__DIR__) . '/bootstrap/app.php';

    $router->dispatch(Request::fromGlobals())->send();
} catch (Throwable $error) {
    // One outermost diagnostic boundary; expected 4xx responses do not throw.
    error_log((string) $error);

    if (! headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
    }

    echo 'Internal Server Error';
}
