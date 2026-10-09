<?php

declare(strict_types=1);

namespace InterviewTask\Accounts\CreateAdmin;

use InterviewTask\Accounts\Account;
use InterviewTask\Accounts\Accounts;
use InterviewTask\Accounts\PasswordRejected;
use InterviewTask\Accounts\UsernameTaken;
use InterviewTask\Accounts\UsernameRejected;
use InvalidArgumentException;
use Psr\Clock\ClockInterface;
use SensitiveParameter;

/**
 * Trusted administrative account creation; not exposed as an HTTP endpoint.
 *
 * @package InterviewTask\Accounts\CreateAdmin
 */
final readonly class CreateAdmin
{
    /**
     * Use the shared Accounts writer and deterministic account creation time.
     *
     * @param Accounts       $accounts Authoritative Accounts owner persistence API.
     * @param ClockInterface $clock    Wall-clock source used for deterministic timestamps.
     */
    public function __construct(
        private Accounts       $accounts,
        private ClockInterface $clock,
    ) {}

    /**
     * Apply standard password policy while intentionally granting the Admin role.
     *
     * @param string $username Username submitted or persisted for the account.
     * @param string $password Secret credential; never log, echo, or include it in diagnostics.
     *
     * @return Account The newly persisted administrator account.
     * @throws InvalidArgumentException When a submitted value or filter violates the documented precondition.
     * @throws PasswordRejected When the password violates the bcrypt input policy.
     * @throws UsernameTaken When the persisted username already exists.
     * @throws UsernameRejected When a new username is outside the ASCII identifier policy.
     */
    public function create(string $username, #[SensitiveParameter] string $password) : Account
    {
        $username = trim(string: $username);

        if ($username === '' || $password === '') {
            throw new InvalidArgumentException(message: 'Username and password are required.');
        }

        return $this->accounts->createAdmin(username: $username, password: $password, createdAt: $this->clock->now());
    }
}
