<?php

declare(strict_types=1);

namespace InterviewTask\Accounts\Login;

use InterviewTask\Accounts\Accounts;
use InterviewTask\Accounts\Access\SessionIdentity;
use InterviewTask\Accounts\Access\SignedInAccount;
use SensitiveParameter;
use Throwable;

/**
 * Authenticate credentials and establish a server-side identity.
 *
 * @package InterviewTask\Accounts\Login
 */
final readonly class Login
{
    /**
     * Use credential verification and the trusted server-session boundary.
     *
     * @param Accounts        $accounts Authoritative Accounts owner persistence API.
     * @param SessionIdentity $identity Server-side authenticated identity access boundary.
     */
    public function __construct(
        private Accounts        $accounts,
        private SessionIdentity $identity,
    ) {}

    /**
     * Reject malformed and invalid credentials with the same public error.
     *
     * @param string $username Username submitted or persisted for the account.
     * @param string $password Secret credential; never log, echo, or include it in diagnostics.
     *
     * @return SignedInAccount The authenticated account snapshot, after session establishment.
     * @throws InvalidCredentials When the submitted credentials cannot authenticate an account.
     * @throws Throwable When credential persistence, rehashing, clock or session establishment fails.
     */
    public function login(string $username, #[SensitiveParameter] string $password) : SignedInAccount
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
