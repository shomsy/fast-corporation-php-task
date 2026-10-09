<?php

declare(strict_types=1);

namespace InterviewTask\Accounts\Persistence;

use InterviewTask\Accounts\Account;

/**
 * Owner-private typed account lookup result, keeping credential material out of the Account snapshot.
 *
 * @package InterviewTask\Accounts\Persistence
 */
final readonly class StoredCredentials
{
    /**
     * @param Account     $account Verified stored account identity, reconstructed from SQLite.
     * @param string|null $hash    Stored credential hash, or null for malformed credential material.
     */
    public function __construct(
        public Account     $account,
        public string|null $hash,
    ) {}
}
