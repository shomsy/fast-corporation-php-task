<?php

declare(strict_types=1);

namespace InterviewTask\Accounts\Login;

use InterviewTask\Accounts\Accounts;
use InterviewTask\Accounts\SessionIdentity;
use InterviewTask\Accounts\SignedInAccount;

final readonly class Login
{
    public function __construct(
        private Accounts        $accounts,
        private SessionIdentity $identity,
    ) {}

    public function login(string $username, #[\SensitiveParameter] string $password): SignedInAccount
    {
        $username = trim(string: $username);

        if ($username === '' || $password === '') {
            throw new InvalidCredentials();
        }

        $account = $this->accounts->authenticate(username: $username, password: $password);

        if ($account === null) {
            throw new InvalidCredentials();
        }

        return $this->identity->signIn(account: $account);
    }
}
