<?php

declare(strict_types=1);

namespace InterviewTask\Accounts;

/**
 * Supported authorization roles persisted in the Accounts table.
 *
 * @package InterviewTask\Accounts
 */
enum Role: string
{
    /**
     * Canonical User value for the Role taxonomy.
         */
    case User  = 'user';
    /**
     * Canonical Admin value for the Role taxonomy.
         */
    case Admin = 'admin';
}
