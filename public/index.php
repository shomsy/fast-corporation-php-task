<?php

declare(strict_types=1);

/**
 * HTTP entry point for the Fast Corporation interview application.
 *
 * Create the route graph and dispatch a request; unexpected exceptions become a safe HTTP 500 and a server diagnostic.
 *
 * @package InterviewTask\Runtime\Http
 */

use InterviewTask\Bootstrap\Http\HealthProbes;
use InterviewTask\Runtime\Http\Request;
use InterviewTask\Runtime\Http\Router;
use InterviewTask\Infrastructure\Diagnostics\SecurityEvents;

// Never expose exception messages or stack traces to HTTP clients.
ini_set(option: 'display_errors', value: '0');

// Autoload the HTTP request boundary before validating an untrusted request-target.
require dirname(path: __DIR__) . '/vendor/autoload.php';

try {
    $request = Request::fromGlobals();
} catch (InvalidArgumentException) {
    // Reject malformed request-targets without ever selecting a business route.
    \InterviewTask\Runtime\Http\Response::text(body: 'Bad Request', status: 400)->send();

    return;
}

try {
    $probe = HealthProbes::respond(request: $request);
    if ($probe !== null) {
        $probe->send();

        return;
    }

    /** @var Router $router */
    $router = require dirname(path: __DIR__) . '/bootstrap/app.php';

    $router->dispatch(request: $request)->send();
} catch (Throwable $error) {
    // One outermost diagnostic boundary; expected 4xx responses do not throw.
    $requestId = SecurityEvents::record(event: 'runtime.unhandled', error: $error);

    if (! headers_sent()) {
        (new \InterviewTask\Runtime\Http\Response(
            body   : 'Internal Server Error',
            status : 500,
            headers: [
                         'Content-Type' => 'text/plain; charset=UTF-8',
                         'X-Request-ID' => $requestId,
                     ],
        ))->send();
    } else {
        echo 'Internal Server Error';
    }
}
