<?php

declare(strict_types=1);

namespace InterviewTask\Accounts;

/**
 * Minimal authenticated identity exposed by the Accounts owner to other modules.
 *
 * @api
 * @package InterviewTask\Accounts
 */
final readonly class SignedInAccount
{
    /**
     * Capture the immutable account ID, display name, and current role.
     *
     * @param int    $id       Input value for id.
     * @param string $username Username submitted or persisted for the account.
     * @param Role   $role     Explicit business role assigned to the account.
     */
    public function __construct(
        public int    $id,
        public string $username,
        public Role   $role,
    ) {}

    /**
     * Project a stored account into the narrow authenticated identity contract.
     *
     * @param Account $account Immutable account from authoritative storage.
     *
     * @return self Narrow public read-only identity projection.
     */
    public static function fromAccount(Account $account) : self
    {
        return new self(id: $account->id, username: $account->username, role: $account->role);
    }
}
