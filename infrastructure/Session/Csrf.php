<?php

declare(strict_types=1);

namespace InterviewTask\Infrastructure\Session;

/**
 * Generate and verify a session-scoped CSRF token for state-changing requests.
 *
 * @package InterviewTask\Infrastructure\Session
 */
final readonly class Csrf
{
    /**
     * Session key used to store the anti-CSRF token.
     *
     * @var string
     */
    private const string SESSION_KEY = 'csrf_token';

    /**
     * Bind CSRF token storage to the session boundary.
     *
     * @param PhpSession $session Trusted server-side PHP session adapter.
     */
    public function __construct(private PhpSession $session) {}

    /**
     * Return the session token or create 256 cryptographically random bits.
     *
     * @return string A persisted session token encoded as hexadecimal.
     */
    public function token(): string
    {
        $stored = $this->session->get(key: self::SESSION_KEY);

        if (is_string(value: $stored) && $stored !== '') {
            return $stored;
        }

        $token = bin2hex(string: random_bytes(length: 32));
        $this->session->put(key: self::SESSION_KEY, value: $token);

        return $token;
    }

    /**
     * Compare the submitted token with the active session token in constant time.
     *
     * @param mixed $submitted Untrusted CSRF token supplied by an HTTP client.
     * @return bool True only when the candidate matches the stored session token.
     */
    public function matches(mixed $submitted): bool
    {
        $stored = $this->session->get(key: self::SESSION_KEY);

        return is_string(value: $submitted)
            && is_string(value: $stored)
            && $stored !== ''
            && hash_equals(known_string: $stored, user_string: $submitted);
    }
}
