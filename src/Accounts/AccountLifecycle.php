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
        private Register $register,
        private Login $login,
        private SessionIdentity $identity,
        private ActivityLog $activity,
        private Transaction $transaction,
    ) {}

    public function register(string $username, string $password): void
    {
        $this->transaction->run(function () use ($username, $password): void {
            $account = $this->register->register($username, $password);
            $this->activity->recordRegistration($account->id, $account->username);
        });
    }

    public function login(string $username, string $password): SignedInAccount
    {
        $account = $this->login->login($username, $password);

        try {
            $this->activity->recordLogin($account->id, $account->username);
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
                $this->activity->recordLogout($account->id, $account->username);
            }
        } finally {
            // Session revocation must not depend on account lookup or audit availability.
            $this->identity->signOut();
        }
    }
}
