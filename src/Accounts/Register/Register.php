<?php

declare(strict_types=1);

namespace InterviewTask\Accounts\Register;

use InterviewTask\Accounts\Account;
use InterviewTask\Accounts\Accounts;
use InterviewTask\Accounts\PasswordRejected;
use Psr\Clock\ClockInterface;

/**
 * Normalize user-provided credentials before creating a regular account.
 *
 * @package InterviewTask\Accounts\Register
 */
final readonly class Register
{
    /**
     * Use the Accounts owner writer and a wall clock.
     *
     * @param Accounts $accounts Authoritative Accounts owner persistence API.
     * @param ClockInterface $clock Wall-clock source used for deterministic timestamps.
     */
    public function __construct(
        private Accounts       $accounts,
        private ClockInterface $clock,
    ) {}

    /**
     * Whitespace-trim the username but never modify the submitted password.
     *
     * @param string $username Username submitted or persisted for the account.
     * @param string $password Secret credential; never log, echo, or include it in diagnostics.
     * @return Account The newly registered user account.
     * @throws RegistrationRejected When registration input or password policy is rejected.
     * @throws \InterviewTask\Accounts\UsernameTaken When the persisted username already exists.
     */
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
