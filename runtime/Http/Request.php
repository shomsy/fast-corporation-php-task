<?php

declare(strict_types=1);

namespace InterviewTask\Runtime\Http;

use InvalidArgumentException;

final readonly class Request
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, mixed> $server
     */
    public function __construct(
        private string $method,
        private string $path,
        private array  $query = [],
        private array  $post = [],
        private array  $server = [],
    )
    {
        if ($path === '' || ! str_starts_with($path, '/')) {
            throw new InvalidArgumentException('Request path must start with "/".');
        }
    }

    public static function fromGlobals() : self
    {
        $uri = is_string($_SERVER['REQUEST_URI'] ?? null)
            ? $_SERVER['REQUEST_URI']
            : '/';

        $path = parse_url($uri, PHP_URL_PATH);

        return new self(
            method: is_string($_SERVER['REQUEST_METHOD'] ?? null) ? $_SERVER['REQUEST_METHOD'] : 'GET',
            path  : is_string($path) && $path !== '' ? $path : '/',
            query : self::stringKeyed($_GET),
            post  : self::stringKeyed($_POST),
            server: self::stringKeyed($_SERVER),
        );
    }

    /**
     * @param array<mixed> $values
     *
     * @return array<string, mixed>
     */
    private static function stringKeyed(array $values) : array
    {
        $result = [];

        foreach ($values as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    public function method() : string
    {
        return strtoupper($this->method);
    }

    public function path() : string
    {
        return $this->path;
    }

    public function query(string $key) : mixed
    {
        return $this->query[$key] ?? null;
    }

    public function post(string $key) : mixed
    {
        return $this->post[$key] ?? null;
    }

    public function server(string $key) : mixed
    {
        return $this->server[$key] ?? null;
    }
}
