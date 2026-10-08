<?php

declare(strict_types=1);

/**
 * HTTP entry point for the Fast Corporation interview application.
 *
 * Create the route graph and dispatch a request; unexpected exceptions become a safe HTTP 500 and a server diagnostic.
 *
 * @package InterviewTask\Runtime\Http
 */

use InterviewTask\Runtime\Http\Request;
use InterviewTask\Runtime\Http\Router;
use InterviewTask\Infrastructure\Diagnostics\SecurityEvents;

// Never expose exception messages or stack traces to HTTP clients.
ini_set(option: 'display_errors', value: '0');

try {
    /** @var Router $router */
    $router = require dirname(path: __DIR__) . '/bootstrap/app.php';

    $router->dispatch(request: Request::fromGlobals())->send();
} catch (Throwable $error) {
    // One outermost diagnostic boundary; expected 4xx responses do not throw.
    $requestId = SecurityEvents::record(event: 'runtime.unhandled', error: $error);

    if (! headers_sent()) {
        (new \InterviewTask\Runtime\Http\Response(
            body: 'Internal Server Error',
            status: 500,
            headers: [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'X-Request-ID' => $requestId,
            ],
        ))->send();
    } else {
        echo 'Internal Server Error';
    }
}
