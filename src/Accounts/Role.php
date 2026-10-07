<?php

declare(strict_types=1);

namespace InterviewTask\Accounts;

enum Role: string
{
    case User = 'user';
    case Admin = 'admin';
}
