<?php

declare(strict_types=1);

namespace InterviewTask\Accounts;

/**
 * Safe cross-owner view of authentication. Session mutation belongs exclusively
 * to the owner-internal lifecycle implementation.
 */
final readonly class CurrentAccount
{
    public function __construct(private SessionIdentity $identity) {}

    public function current(): ?SignedInAccount
    {
        return $this->identity->current();
    }
}
