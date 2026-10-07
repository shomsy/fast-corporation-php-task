<?php

declare(strict_types=1);

namespace InterviewTask\Accounts\Login;

use DomainException;

final class InvalidCredentials extends DomainException
{
    public function __construct()
    {
        parent::__construct('Invalid username or password.');
    }
}
