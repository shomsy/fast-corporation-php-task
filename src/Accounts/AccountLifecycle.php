<?php

declare(strict_types=1);

namespace InterviewTask\Accounts;

use InterviewTask\Accounts\Login\Login;
use InterviewTask\Accounts\Register\Register;
use InterviewTask\ActivityTracking\ActivityLog;
use InterviewTask\Infrastructure\Database\Transaction;
use Throwable;

/**
 * Accepted account access outcomes include their required activity history.
 *
 * @api
 * @package InterviewTask\Accounts
 * @see \InterviewTask\ActivityTracking\ActivityLog
 * @see \InterviewTask\Infrastructure\Database\Transaction
 */
final readonly class AccountLifecycle
{
    /**
     * Wire the account behaviors, identity boundary, audit writer, and transaction.
     *
     * @param Register $register Account registration behavior.
     * @param Login $login Account sign-in behavior.
     * @param SessionIdentity $identity Server-side authenticated identity access boundary.
     * @param ActivityLog $activity Authoritative activity-event writer.
     * @param Transaction $transaction Transaction boundary for coordinated account and event writes.
     */
    public function __construct(
        private Register        $register,
        private Login           $login,
        private SessionIdentity $identity,
        private ActivityLog     $activity,
        private Transaction     $transaction,
    ) {}

    /**
     * Atomically create the account and its registration audit event. No successful registration may exist without its required history.
     *
     * @param string $username Username submitted or persisted for the account.
     * @param string $password Secret credential; never log, echo, or include it in diagnostics.
     * @return void No return value.
     * @throws \InterviewTask\Accounts\Register\RegistrationRejected When registration input or password policy is rejected.
     * @throws UsernameTaken When the persisted username already exists.
     * @throws \PDOException When account creation or audit persistence fails; the transaction rolls back.
     */
    public function register(string $username, #[\SensitiveParameter] string $password): void
    {
        $this->transaction->run(operation: function () use ($username, $password): void {
            $account = $this->register->register(username: $username, password: $password);
            $this->activity->recordRegistration(actorId: $account->id, actorLabel: $account->username);
        });
    }

    /**
     * Authenticate, establish a session and record login activity. Revoke the new session if auditing fails; never report partial success.
     *
     * @param string $username Username submitted or persisted for the account.
     * @param string $password Secret credential; never log, echo, or include it in diagnostics.
     * @return SignedInAccount The verified account identity exposed to the caller.
     * @throws \InterviewTask\Accounts\Login\InvalidCredentials When the submitted credentials cannot authenticate an account.
     * @throws \PDOException When login activity persistence fails; the new session is revoked.
     */
    public function login(string $username, #[\SensitiveParameter] string $password): SignedInAccount
    {
        $account = $this->login->login(username: $username, password: $password);

        try {
            $this->activity->recordLogin(actorId: $account->id, actorLabel: $account->username);
        } catch (Throwable $error) {
            $this->identity->signOut();
            throw $error;
        }

        return $account;
    }

    /**
     * Revoke the session even if account lookup or logout auditing fails. An operational failure is still propagated to the HTTP error boundary.
     *
     * @return void No return value.
     * @throws \PDOException When account reloading or logout activity persistence fails; session revocation still occurs.
     */
    public function logout(): void
    {
        try {
            $account = $this->identity->current();

            if ($account !== null) {
                $this->activity->recordLogout(actorId: $account->id, actorLabel: $account->username);
            }
        } finally {
            // Session revocation must not depend on account lookup or audit availability.
            $this->identity->signOut();
        }
    }
}
