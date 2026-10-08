<?php

declare(strict_types=1);

namespace InterviewTask\Accounts\CreateAdmin;

use InterviewTask\Accounts\Account;
use InterviewTask\Accounts\Accounts;
use InvalidArgumentException;
use Psr\Clock\ClockInterface;

final readonly class CreateAdmin
{
    public function __construct(
        private Accounts $accounts,
        private ClockInterface $clock,
    ) {}

    public function create(string $username, string $password): Account
    {
        $username = trim($username);

        if ($username === '' || $password === '') {
            throw new InvalidArgumentException('Username and password are required.');
        }

        return $this->accounts->createAdmin($username, $password, $this->clock->now());
    }
}
