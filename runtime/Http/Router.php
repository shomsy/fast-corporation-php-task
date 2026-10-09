<?php

declare(strict_types=1);

namespace InterviewTask\Runtime\Http;

use Closure;
use InvalidArgumentException;

/**
 * Exact-path router. Dynamic route matching is intentionally deferred until a real behavior requires it.
 *
 * @package InterviewTask\Runtime\Http
 * @see     Request
 * @see     Response
 */
final class Router
{
    /**
     * Exact registered method/path handlers, including their explicit access policies.
     *
     * @var array<string, array<string, array{handler: Closure(Request): Response, access: RouteAccess}>>
     */
    private array $routes = [];

    /**
     * Central application security gate applied to all registered routes.
     *
     * @var (Closure(Request, RouteAccess): (Response|null))|null
     */
    private Closure|null $guard = null;

    /**
     * Configure the single security boundary for route authorization and unsafe methods.
     *
     * @param callable(Request, RouteAccess): (Response|null) $guard Denial response or null when permitted.
     *
     * @return void No return value.
     */
    public function guardWith(callable $guard) : void
    {
        $this->guard = Closure::fromCallable(callback: $guard);
    }

    /**
     * Register or replace an exact HTTP method-and-path handler.
     *
     * @param string                      $method  HTTP request method or route verb.
     * @param string                      $path    Exact HTTP route path beginning with /; never a filesystem path.
     * @param callable(Request): Response $handler Handler invoked only after centralized authorization and CSRF.
     * @param RouteAccess                 $access  Required permission; defaults to signed-in, never public.
     *
     * @return void No return value.
     * @throws InvalidArgumentException When a submitted value or filter violates the documented precondition.
     */
    public function add(string $method, string $path, callable $handler, RouteAccess $access = RouteAccess::SignedIn) : void
    {
        $method = strtoupper(string: trim(string: $method));

        if ($method === '') {
            throw new InvalidArgumentException(message: 'HTTP method cannot be empty.');
        }

        if ($path === '' || ! str_starts_with(haystack: $path, needle: '/')) {
            throw new InvalidArgumentException(message: 'Route path must start with "/".');
        }

        $this->routes[$method][$path] = [
            'handler' => Closure::fromCallable(callback: $handler),
            'access'  => $access,
        ];
    }

    /**
     * Invoke a matching handler or return 404/405 with an Allow header.
     *
     * @param Request $request Raw HTTP request; validate client-supplied values before use.
     *
     * @return Response Matched response, or 404/405 without invoking an invalid handler.
     */
    public function dispatch(Request $request) : Response
    {
        $method  = $request->method();
        $path    = $request->path();
        $allowed = [];

        foreach ($this->routes as $registeredMethod => $registeredRoutes) {
            if (array_key_exists(key: $path, array: $registeredRoutes)) {
                $allowed[] = $registeredMethod;
            }
        }

        if (in_array(needle: 'GET', haystack: $allowed, strict: true)) {
            $allowed[] = 'HEAD';
        }
        if ($allowed !== []) {
            $allowed[] = 'OPTIONS';
        }
        $allowed  = array_values(array_unique(array: $allowed));
        $priority = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];
        $rank     = array_flip(array: $priority);
        usort(array: $allowed, callback: static fn (string $a, string $b) : int => ($rank[$a] ?? 99) <=> ($rank[$b] ?? 99));

        if ($method === 'OPTIONS' && $allowed !== []) {
            return new Response(body: '', status: 204, headers: ['Allow' => implode(separator: ', ', array: $allowed)]);
        }

        $route = $this->routes[$method][$path] ?? ($method === 'HEAD' ? ($this->routes['GET'][$path] ?? null) : null);

        if ($route !== null) {
            if ($this->guard === null) {
                // No application security binding: protected routes and all unsafe verbs fail closed.
                if ($route['access'] !== RouteAccess::Public || ! in_array(needle: $request->method(), haystack: ['GET', 'HEAD', 'OPTIONS'], strict: true)) {
                    return $method === 'HEAD' ? new Response(body: '', status: 403) : Response::text(body: 'Forbidden', status: 403);
                }
            } else {
                $denied = ($this->guard)($request, $route['access']);

                if ($denied !== null) {
                    return $method === 'HEAD'
                        ? new Response(body: '', status: $denied->status(), headers: $denied->headers())
                        : $denied;
                }
            }

            // Tracking GET handlers must never be invoked by HEAD (no view event).
            if ($method === 'HEAD') {
                return new Response(body: '', status: 200, headers: ['Content-Type' => 'text/html; charset=UTF-8']);
            }

            return ($route['handler'])($request);
        }

        if ($allowed !== []) {
            return new Response(body: 'Method Not Allowed', status: 405, headers: [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Allow'        => implode(separator: ', ', array: $allowed),
            ]);
        }

        return Response::text(body: 'Not Found', status: 404);
    }
}
