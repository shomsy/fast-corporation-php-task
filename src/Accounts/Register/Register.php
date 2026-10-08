<?php

declare(strict_types=1);

namespace InterviewTask\Accounts\Register;

use InterviewTask\Accounts\Account;
use InterviewTask\Accounts\Accounts;
use InterviewTask\Accounts\PasswordRejected;
use Psr\Clock\ClockInterface;

final readonly class Register
{
    public function __construct(
        private Accounts       $accounts,
        private ClockInterface $clock,
    ) {}

    public function register(string $username, #[\SensitiveParameter] string $password): Account
    {
        $username = trim(string: $username);

        if ($username === '' || $password === '') {
            throw new RegistrationRejected(message: 'Username and password are required.');
        }

        try {
            return $this->accounts->createUser(username: $username, password: $password, createdAt: $this->clock->now());
        } catch (PasswordRejected $e) {
            throw new RegistrationRejected(message: $e->getMessage(), previous: $e);
        }
    }
}
