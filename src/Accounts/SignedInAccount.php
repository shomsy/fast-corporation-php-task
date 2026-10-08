<?php

declare(strict_types=1);

namespace InterviewTask\Accounts;

final readonly class SignedInAccount
{
    public function __construct(
        public int    $id,
        public string $username,
        public Role   $role,
    ) {}

    public static function fromAccount(Account $account): self
    {
        return new self(id: $account->id, username: $account->username, role: $account->role);
    }
}
