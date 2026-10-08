<?php

declare(strict_types=1);

namespace InterviewTask\Accounts;

final readonly class RequireAdmin
{
    public function __construct(private SessionIdentity $identity) {}

    public function account() : SignedInAccount
    {
        $account = $this->identity->current();

        if ($account === null || $account->role !== Role::Admin) {
            throw new AccessDenied('Administrator access required.');
        }

        return $account;
    }
}
