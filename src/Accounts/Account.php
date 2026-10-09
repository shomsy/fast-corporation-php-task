<?php

declare(strict_types=1);

namespace InterviewTask\Accounts;

use DateTimeImmutable;

/**
 * Internal snapshot reconstructed from the Accounts owner's stored facts.
 *
 * @package InterviewTask\Accounts
 */
final readonly class Account
{
    /**
     * Capture immutable account data from the authoritative storage owner.
     *
     * @param int               $id        Input value for id.
     * @param string            $username  Username submitted or persisted for the account.
     * @param Role              $role      Explicit business role assigned to the account.
     * @param DateTimeImmutable $createdAt Immutable creation time used in persistence.
     */
    public function __construct(
        public int               $id,
        public string            $username,
        public Role              $role,
        public DateTimeImmutable $createdAt,
    ) {}
}
