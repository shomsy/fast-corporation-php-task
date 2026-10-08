<?php

declare(strict_types=1);

namespace InterviewTask\Infrastructure\Session;

final readonly class Csrf
{
    private const string SESSION_KEY = 'csrf_token';

    public function __construct(private PhpSession $session) {}

    public function token(): string
    {
        $stored = $this->session->get(self::SESSION_KEY);

        if (is_string($stored) && $stored !== '') {
            return $stored;
        }

        $token = bin2hex(random_bytes(32));
        $this->session->put(self::SESSION_KEY, $token);

        return $token;
    }

    public function matches(mixed $submitted): bool
    {
        $stored = $this->session->get(self::SESSION_KEY);

        return is_string($submitted)
            && is_string($stored)
            && $stored !== ''
            && hash_equals($stored, $submitted);
    }
}
