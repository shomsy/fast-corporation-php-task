<?php

declare(strict_types=1);

namespace InterviewTask\Runtime\Http;

use InvalidArgumentException;

/**
 * Immutable outgoing HTTP status, headers and body, emitted at the outer boundary.
 *
 * @package InterviewTask\Runtime\Http
 */
final readonly class Response
{
    /**
     * Capture outgoing status, headers and body after validating the status code.
     *
     * @param string $body HTTP response payload.
     * @param int $status HTTP response status code.
     * @param array<string, string> $headers
     * @throws \InvalidArgumentException When a submitted value or filter violates the documented precondition.
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

    /**
     * Build a text response with a Content-Type header.
     *
     * @param string $body HTTP response payload.
     * @param int $status HTTP response status code.
     * @return self A plain-text response with the supplied status.
     */
    public static function text(string $body, int $status = 200): self
    {
        return new self(body: $body, status: $status, headers: ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /**
     * Build an HTML response with a Content-Type header.
     *
     * @param string $body HTTP response payload.
     * @param int $status HTTP response status code.
     * @return self An HTML response with the supplied status.
     */
    public static function html(string $body, int $status = 200): self
    {
        return new self(body: $body, status: $status, headers: ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * Build a redirect response with the requested Location.
     *
     * @param string $location Redirect destination (Location header).
     * @param int $status HTTP response status code.
     * @return self A redirect response with a Location header.
     */
    public static function redirect(string $location, int $status = 303): self
    {
        return new self(body: '', status: $status, headers: ['Location' => $location]);
    }

    /**
     * Read the response body without emitting it.
     *
     * @return string Body that will be sent to the HTTP client.
     */
    public function body(): string
    {
        return $this->body;
    }

    /**
     * Read the numeric HTTP status.
     *
     * @return int Validated HTTP status code.
     */
    public function status(): int
    {
        return $this->status;
    }

    /**
     * Read the outgoing response header mapping.
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Emit the status, HTTP headers and body from the entry point.
     *
     * @return void No return value.
     */
    public function send(): void
    {
        http_response_code(response_code: $this->status);

        foreach ($this->headers as $name => $value) {
            header(header: $name . ': ' . $value);
        }

        echo $this->body;
    }
}
