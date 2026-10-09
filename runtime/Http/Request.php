<?php

declare(strict_types=1);

namespace InterviewTask\Runtime\Http;

use InvalidArgumentException;
use JsonException;
use stdClass;

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
     * @param string $path Parsed HTTP request pathname, never a filesystem path.
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, mixed> $server
     * @param ?string $rawBody Optional injected JSON body for deterministic tests.
     * @throws InvalidArgumentException When a submitted value or filter violates the documented precondition.
     */
    public function __construct(
        private string $method,
        private string $path,
        private array  $query = [],
        private array  $post = [],
        private array  $server = [],
        private ?string $rawBody = null,
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

        // A malformed request-target is a 400, never a silent fallback to '/'.
        if (strlen(string: $uri) > 4096 || ! str_starts_with(haystack: $uri, needle: '/')
            || str_starts_with(haystack: $uri, needle: '//')
            || preg_match(pattern: '/[\\x00-\\x20\\x7f\\\\#]/', subject: $uri) === 1
            || preg_match(pattern: '/%(?![a-fA-F0-9]{2})/', subject: $uri) === 1) {
            throw new InvalidArgumentException(message: 'Invalid request target.');
        }

        $parts = parse_url(url: $uri);

        if (! is_array(value: $parts) || isset($parts['scheme'], $parts['host'])
            || isset($parts['fragment']) || ! is_string(value: $parts['path'] ?? null)
            || $parts['path'] === '' || str_starts_with(haystack: $parts['path'], needle: '//')
            || preg_match(pattern: '/[\\x00-\\x1f\\x7f\\\\]/', subject: rawurldecode(string: $parts['path'])) === 1) {
            throw new InvalidArgumentException(message: 'Invalid request target.');
        }

        $path = $parts['path'];

        return new self(
            method: is_string(value: $_SERVER['REQUEST_METHOD'] ?? null) ? $_SERVER['REQUEST_METHOD'] : 'GET',
            path  : $path,
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
     * Obtain a CSRF submission from the correct transport without mixing browser forms and JSON APIs.
     *
     * @return mixed Submitted session token or null when the transport is inappropriate.
     */
    public function csrfToken(): mixed
    {
        if (str_starts_with(haystack: $this->path, needle: '/api/')) {
            $contentType = $this->server(key: 'CONTENT_TYPE');

            if (! is_string(value: $contentType)
                || strtolower(string: trim(string: explode(separator: ';', string: $contentType, limit: 2)[0])) !== 'application/json') {
                return null;
            }

            return $this->server(key: 'HTTP_X_CSRF_TOKEN');
        }

        return $this->post(key: 'csrf_token');
    }

    /**
     * Parse a bounded JSON object from an API request without coercing client values.
     *
     * @return array<string, mixed> Untrusted JSON properties for the owning adapter to validate.
     * @throws InvalidArgumentException When the transport, JSON or payload size is invalid.
     */
    public function jsonObject(): array
    {
        $contentType = $this->server(key: 'CONTENT_TYPE');

        if (! str_starts_with(haystack: $this->path, needle: '/api/')
            || ! is_string(value: $contentType)
            || strtolower(string: trim(string: explode(separator: ';', string: $contentType, limit: 2)[0])) !== 'application/json') {
            throw new InvalidArgumentException(message: 'JSON request content type required.');
        }

        $body = $this->rawBody ?? file_get_contents(filename: 'php://input', offset: 0, length: 16385);

        if (! is_string(value: $body) || strlen(string: $body) > 16384) {
            throw new InvalidArgumentException(message: 'JSON body too large.');
        }

        try {
            $decoded = json_decode(json: $body, associative: false, depth: 16, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException(message: 'Invalid JSON body.');
        }

        if (! $decoded instanceof stdClass) {
            throw new InvalidArgumentException(message: 'JSON object required.');
        }

        /** @var array<string, mixed> $fields */
        $fields = get_object_vars(object: $decoded);

        return $fields;
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
