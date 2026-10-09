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
     * @throws InvalidArgumentException When a submitted value or filter violates the documented precondition.
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
     * Encode a trusted PHP payload as an HTML-safe JSON HTTP response.
     *
     * @param array<string, mixed> $payload Transport projection, never a raw domain object.
     * @param int $status Response status code.
     * @return self JSON response with explicit content type.
     * @throws \JsonException When payload serialization is invalid.
     */
    public static function json(array $payload, int $status = 200): self
    {
        return new self(
            body: json_encode(value: $payload, flags: JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS),
            status: $status,
            headers: ['Content-Type' => 'application/json; charset=UTF-8'],
        );
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
        // One boundary for private dynamic responses: never depend on every handler
        // remembering browser security, privacy or HTTPS policy.
        $defaults = [
            'Cache-Control'           => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options'        => 'DENY',
            'Referrer-Policy'        => 'no-referrer',
            // SSR still embeds CSS. Scripts are only allowed as same-origin files.
            'Content-Security-Policy' => "default-src 'none'; script-src 'self'; connect-src 'self'; style-src 'self' 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'",
        ];

        if (in_array(needle: $_SERVER['HTTPS'] ?? null, haystack: ['on', '1'], strict: true)) {
            $defaults['Strict-Transport-Security'] = 'max-age=31536000';
        }

        return array_replace($defaults, $this->headers);
    }

    /**
     * Create an immutable copy with an additional trusted response header.
     *
     * @param string $name HTTP response header name.
     * @param string $value Server-created header value.
     * @return self Response copy including the new header.
     * @throws InvalidArgumentException When the header contains forbidden characters.
     */
    public function withHeader(string $name, string $value): self
    {
        if (preg_match(pattern: '/\A[A-Za-z0-9-]+\z/D', subject: $name) !== 1
            || str_contains(haystack: $value, needle: "\r")
            || str_contains(haystack: $value, needle: "\n")) {
            throw new InvalidArgumentException(message: 'Invalid response header.');
        }

        return new self(body: $this->body, status: $this->status, headers: array_replace($this->headers, [$name => $value]));
    }

    /**
     * Emit the status, HTTP headers and body from the entry point.
     *
     * @return void No return value.
     */
    public function send(): void
    {
        http_response_code(response_code: $this->status);

        foreach ($this->headers() as $name => $value) {
            header(header: $name . ': ' . $value);
        }

        // HEAD and no-content status codes may carry headers but never a message body.
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'HEAD'
            && ! in_array(needle: $this->status, haystack: [204, 304], strict: true)) {
            echo $this->body;
        }
    }
}
