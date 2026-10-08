<?php

declare(strict_types=1);

namespace InterviewTask\Accounts;

use DomainException;
use Throwable;

final class UsernameTaken extends DomainException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct(message: 'Username is already taken.', previous: $previous);
    }
}
