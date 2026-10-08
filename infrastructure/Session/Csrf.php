<?php

declare(strict_types=1);

namespace InterviewTask\Infrastructure\Session;

final readonly class Csrf
{
    private const string SESSION_KEY = 'csrf_token';

    public function __construct(private PhpSession $session) {}

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

    public function matches(mixed $submitted): bool
    {
        $stored = $this->session->get(key: self::SESSION_KEY);

        return is_string(value: $submitted)
            && is_string(value: $stored)
            && $stored !== ''
            && hash_equals(known_string: $stored, user_string: $submitted);
    }
}
