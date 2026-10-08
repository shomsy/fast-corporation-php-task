<?php

declare(strict_types=1);

namespace InterviewTask\Accounts;

use InterviewTask\Accounts\Login\Login;
use InterviewTask\Accounts\Register\Register;
use InterviewTask\ActivityTracking\ActivityLog;
use InterviewTask\Infrastructure\Database\Transaction;
use Throwable;

/** Accepted account access outcomes include their required activity history. */
final readonly class AccountLifecycle
{
    public function __construct(
        private Register        $register,
        private Login           $login,
        private SessionIdentity $identity,
        private ActivityLog     $activity,
        private Transaction     $transaction,
    ) {}

    public function register(string $username, #[\SensitiveParameter] string $password): void
    {
        $this->transaction->run(operation: function () use ($username, $password): void {
            $account = $this->register->register(username: $username, password: $password);
            $this->activity->recordRegistration(actorId: $account->id, actorLabel: $account->username);
        });
    }

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
