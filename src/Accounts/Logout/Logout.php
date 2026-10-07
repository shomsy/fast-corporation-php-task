<?php

declare(strict_types=1);

namespace InterviewTask\Accounts\Logout;

use InterviewTask\Accounts\SessionIdentity;

final readonly class Logout
{
    public function __construct(private SessionIdentity $identity) {}

    public function logout(): void
    {
        $this->identity->signOut();
    }
}
