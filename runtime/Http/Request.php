<?php

declare(strict_types=1);

namespace InterviewTask\Runtime\Http;

use InvalidArgumentException;

/**
 * Immutable HTTP request boundary; input values remain untrusted until parsed.
 *
 * @package InterviewTask\Runtime\Http
 */
final readonly class Request
{
    /**
     * Capture the HTTP method, path and raw input containers without coercing values.
     *
     * @param string $method HTTP request method or route verb.
     * @param string $path Absolute route, request path, or trusted local filesystem path.
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, mixed> $server
     * @throws InvalidArgumentException When a submitted value or filter violates the documented precondition.
     */
    public function __construct(
        private string $method,
        private string $path,
        private array  $query = [],
        private array  $post = [],
        private array  $server = [],
    ) {
        if ($path === '' || ! str_starts_with(haystack: $path, needle: '/')) {
            throw new InvalidArgumentException(message: 'Request path must start with "/".');
        }
    }

    /**
     * Create a request snapshot from PHP superglobals.
     *
     * @return self Immutable request snapshot from the current superglobals.
     */
    public static function fromGlobals(): self
    {
        $uri = is_string(value: $_SERVER['REQUEST_URI'] ?? null)
            ? $_SERVER['REQUEST_URI']
            : '/';

        $path = parse_url(url: $uri, component: PHP_URL_PATH);

        return new self(
            method: is_string(value: $_SERVER['REQUEST_METHOD'] ?? null) ? $_SERVER['REQUEST_METHOD'] : 'GET',
            path  : is_string(value: $path) && $path !== '' ? $path : '/',
            query : self::stringKeyed(values: $_GET),
            post  : self::stringKeyed(values: $_POST),
            server: self::stringKeyed(values: $_SERVER),
        );
    }

    /**
     * Discard non-string top-level input keys without changing values.
     *
     * @param array<array-key, mixed> $values
     * @return array<string, mixed>
     */
    private static function stringKeyed(array $values): array
    {
        $result = [];

        foreach ($values as $key => $value) {
            if (is_string(value: $key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Return the uppercase HTTP request method.
     *
     * @return string Uppercase HTTP method.
     */
    public function method(): string
    {
        return strtoupper(string: $this->method);
    }

    /**
     * Return the exact request path used for route matching.
     *
     * @return string Absolute request path.
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * Fetch a raw query-string value for transport-boundary validation.
     *
     * @param string $key Name of a session or HTTP input value.
     * @return mixed Raw query input for the named key, or null if missing.
     */
    public function query(string $key): mixed
    {
        return $this->query[$key] ?? null;
    }

    /**
     * Fetch a raw submitted form value for transport-boundary validation.
     *
     * @param string $key Name of a session or HTTP input value.
     * @return mixed Raw form input for the named key, or null if missing.
     */
    public function post(string $key): mixed
    {
        return $this->post[$key] ?? null;
    }

    /**
     * Fetch untrusted HTTP server metadata.
     *
     * @param string $key Name of a session or HTTP input value.
     * @return mixed Raw request metadata, or null if missing.
     */
    public function server(string $key): mixed
    {
        return $this->server[$key] ?? null;
    }
    /**
     * Recognize speculative browser prefetches; this is never an authorization signal.
     *
     * @return bool Whether a standard prefetch marker is present.
     */
    public function isPrefetch(): bool
    {
        foreach (['HTTP_SEC_PURPOSE', 'HTTP_PURPOSE', 'HTTP_X_MOZ'] as $name) {
            $header = $this->server(key: $name);

            if (is_string(value: $header)
                && preg_match(pattern: '/(?:^|[;,\\s])prefetch(?:$|[;,\\s])/i', subject: $header) === 1) {
                return true;
            }
        }

        return false;
    }

}
