<?php

declare(strict_types=1);

namespace InterviewTask\Runtime\Http;

use InvalidArgumentException;

final readonly class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private string $body = '',
        private int    $status = 200,
        private array  $headers = [],
    ) {
        if ($status < 100 || $status > 599) {
            throw new InvalidArgumentException(message: 'HTTP status must be between 100 and 599.');
        }
    }

    public static function text(string $body, int $status = 200): self
    {
        return new self(body: $body, status: $status, headers: ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self(body: $body, status: $status, headers: ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function redirect(string $location, int $status = 303): self
    {
        return new self(body: '', status: $status, headers: ['Location' => $location]);
    }

    public function body(): string
    {
        return $this->body;
    }

    public function status(): int
    {
        return $this->status;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    public function send(): void
    {
        http_response_code(response_code: $this->status);

        foreach ($this->headers as $name => $value) {
            header(header: $name . ': ' . $value);
        }

        echo $this->body;
    }
}
