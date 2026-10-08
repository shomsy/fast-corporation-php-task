<?php

declare(strict_types=1);

namespace InterviewTask\Runtime\Http;

use Closure;
use InvalidArgumentException;

/**
 * Exact-path router. Dynamic route matching is intentionally deferred until a real behavior requires it.
 *
 * @package InterviewTask\Runtime\Http
 * @see Request
 * @see Response
 */
final class Router
{
    /**
     * Internal state or dependency owned by Router.
     *
     * @var array<string, array<string, Closure(Request): Response>>
     */
    private array $routes = [];

    /**
     * Register or replace an exact HTTP method-and-path handler.
     *
     * @param string $method HTTP request method or route verb.
     * @param string $path Absolute route, request path, or trusted local filesystem path.
     * @param callable(Request): Response $handler
     * @return void No return value.
     * @throws InvalidArgumentException When a submitted value or filter violates the documented precondition.
     */
    public function add(string $method, string $path, callable $handler): void
    {
        $method = strtoupper(string: trim(string: $method));

        if ($method === '') {
            throw new InvalidArgumentException(message: 'HTTP method cannot be empty.');
        }

        if ($path === '' || ! str_starts_with(haystack: $path, needle: '/')) {
            throw new InvalidArgumentException(message: 'Route path must start with "/".');
        }

        $this->routes[$method][$path] = Closure::fromCallable(callback: $handler);
    }

    /**
     * Invoke a matching handler or return 404/405 with an Allow header.
     *
     * @param Request $request Raw HTTP request; validate client-supplied values before use.
     * @return Response Matched response, or 404/405 without invoking an invalid handler.
     */
    public function dispatch(Request $request): Response
    {
        $handler = $this->routes[$request->method()][$request->path()] ?? null;

        if ($handler !== null) {
            return $handler($request);
        }

        $allowed = [];
        foreach ($this->routes as $method => $routesForMethod) {
            if (array_key_exists(key: $request->path(), array: $routesForMethod)) {
                $allowed[] = $method;
            }
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
