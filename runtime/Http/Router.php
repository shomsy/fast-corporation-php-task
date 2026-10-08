<?php

declare(strict_types=1);

namespace InterviewTask\Runtime\Http;

use Closure;
use InvalidArgumentException;

/**
 * Exact-path router. Dynamic route matching is intentionally deferred until a
 * real behavior requires it.
 */
final class Router
{
    /**
     * @var array<string, array<string, Closure(Request): Response>>
     */
    private array $routes = [];

    /**
     * @param callable(Request): Response $handler
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
