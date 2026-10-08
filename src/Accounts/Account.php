<?php

declare(strict_types=1);

namespace InterviewTask\Accounts;

use DateTimeImmutable;

final readonly class Account
{
    public function __construct(
        public int               $id,
        public string            $username,
        public Role              $role,
        public DateTimeImmutable $createdAt,
    ) {}
}
