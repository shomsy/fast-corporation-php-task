<?php

declare(strict_types=1);

namespace InterviewTask\Accounts;

final readonly class RequireSignedIn
{
    public function __construct(private SessionIdentity $identity) {}

    public function account() : SignedInAccount
    {
        return $this->identity->current()
            ?? throw new AccessDenied('Authentication required.');
    }
}
