<?php

declare(strict_types=1);

namespace InterviewTask\Accounts;

use InterviewTask\Accounts\Access\SessionIdentity;
use InterviewTask\Accounts\Access\SignedInAccount;

use InterviewTask\Accounts\Login\InvalidCredentials;
use InterviewTask\Accounts\Login\Login;
use InterviewTask\Accounts\Register\Register;
use InterviewTask\Accounts\Register\RegistrationRejected;
use InterviewTask\ActivityTracking\ActivityLog;
use InterviewTask\Infrastructure\Database\Transaction;
use InterviewTask\Infrastructure\Diagnostics\CleanupFailures;
use LogicException;
use PDOException;
use SensitiveParameter;
use Throwable;

/**
 * Accepted account access outcomes include their required activity history.
 *
 * @api
 * @package InterviewTask\Accounts
 * @see     ActivityLog
 * @see     Transaction
 */
final readonly class AccountLifecycle
{
    /**
     * Wire the account behaviors, identity boundary, audit writer, and transaction.
     *
     * @param Register        $register        Account registration behavior.
     * @param Login           $login           Account sign-in behavior.
     * @param SessionIdentity $identity        Server-side authenticated identity access boundary.
     * @param ActivityLog     $activity        Authoritative activity-event writer.
     * @param Transaction     $transaction     Transaction boundary for coordinated account and event writes.
     * @param CleanupFailures $cleanupFailures Best-effort reporting of secondary session cleanup failures.
     */
    public function __construct(
        private Register        $register,
        private Login           $login,
        private SessionIdentity $identity,
        private ActivityLog     $activity,
        private Transaction     $transaction,
        private CleanupFailures $cleanupFailures,
    ) {}

    /**
     * Atomically create the account and its registration audit event. No successful registration may exist without its
     * required history.
     *
     * @param string $username Username submitted or persisted for the account.
     * @param string $password Secret credential; never log, echo, or include it in diagnostics.
     *
     * @return void No return value.
     * @throws RegistrationRejected When registration input or password policy is rejected.
     * @throws UsernameTaken When the persisted username already exists.
     * @throws PDOException When account creation or audit persistence fails; rollback is attempted.
     * @throws LogicException When an existing transaction prevents lifecycle ownership.
     * @throws Throwable When an operational dependency fails; rollback is attempted and the original failure takes
     *                   precedence.
     */
    public function register(string $username, #[SensitiveParameter] string $password) : void
    {
        $this->transaction->run(operation: function () use ($username, $password) : void {
            $account = $this->register->register(username: $username, password: $password);
            $this->activity->recordRegistration(actorId: $account->id, actorLabel: $account->username);
        });
    }

    /**
     * Authenticate, establish a session and record login activity. Revoke the new session if auditing fails; never
     * report partial success.
     *
     * @param string $username Username submitted or persisted for the account.
     * @param string $password Secret credential; never log, echo, or include it in diagnostics.
     *
     * @return SignedInAccount The verified account identity exposed to the caller.
     * @throws InvalidCredentials When the submitted credentials cannot authenticate an account.
     * @throws PDOException When login activity persistence fails; session revocation is attempted.
     * @throws Throwable When credential lookup or session establishment fails, or auditing fails and revocation is
     *                   attempted.
     */
    public function login(string $username, #[SensitiveParameter] string $password) : SignedInAccount
    {
        $account = $this->login->login(username: $username, password: $password);

        try {
            $this->activity->recordLogin(actorId: $account->id, actorLabel: $account->username);
        } catch (Throwable $error) {
            try {
                $this->identity->signOut();
            } catch (Throwable $cleanupError) {
                $this->cleanupFailures->record(error: $cleanupError);
            }

            throw $error;
        }

        return $account;
    }

    /**
     * Revoke the session even if account lookup or logout auditing fails. An operational failure is still propagated
     * to the HTTP error boundary.
     *
     * @return void No return value.
     * @throws PDOException When account reloading or logout activity persistence fails; session revocation is still
     *                      attempted.
     * @throws Throwable When session revocation or another operational dependency fails; the original failure takes
     *                   precedence.
     */
    public function logout() : void
    {
        $failure = null;

        try {
            $account = $this->identity->current();

            if ($account !== null) {
                $this->activity->recordLogout(actorId: $account->id, actorLabel: $account->username);
            }
        } catch (Throwable $error) {
            $failure = $error;
        }

        // Always attempt revocation. Prefer the original audit/lookup error when cleanup also fails.
        try {
            $this->identity->signOut();
        } catch (Throwable $cleanupError) {
            if ($failure === null) {
                throw $cleanupError;
            }

            $this->cleanupFailures->record(error: $cleanupError);
        }

        if ($failure !== null) {
            throw $failure;
        }
    }

}
