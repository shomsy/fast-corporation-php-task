<?php

declare(strict_types=1);

namespace InterviewTask\Accounts;

use InterviewTask\Infrastructure\Session\PhpSession;

final readonly class SessionIdentity
{
    private const string ACCOUNT_ID = 'account_id';

    public function __construct(
        private PhpSession $session,
        private Accounts $accounts,
    ) {}

    public function signIn(Account $account): SignedInAccount
    {
        $this->session->regenerate();
        $this->session->put(self::ACCOUNT_ID, $account->id);

        return SignedInAccount::fromAccount($account);
    }

    public function current(): ?SignedInAccount
    {
        $id = $this->session->get(self::ACCOUNT_ID);

        if (!is_int($id)) {
            return null;
        }

        $account = $this->accounts->findById($id);

        if ($account === null) {
            $this->session->remove(self::ACCOUNT_ID);

            return null;
        }

        return SignedInAccount::fromAccount($account);
    }

    public function signOut(): void
    {
        $this->session->destroy();
    }
}
