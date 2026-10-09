<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Accounts;

use InterviewTask\Accounts\Role;
use InterviewTask\Accounts\SignedInAccount;
use PHPUnit\Framework\TestCase;

/**
 * Assert the exported account identity can answer access questions without leaking role implementation to HTTP.
 *
 * @package InterviewTask\Tests\Accounts
 */
final class SignedInAccountTest extends TestCase
{
    /**
     * Verify privileges for both canonical business roles.
     *
     * @return void No return value.
     */
    public function testOnlyAdministratorsHaveAdministratorPrivileges() : void
    {
        self::assertFalse((new SignedInAccount(id: 1, username: 'user', role: Role::User))->isAdmin());
        self::assertTrue((new SignedInAccount(id: 2, username: 'admin', role: Role::Admin))->isAdmin());
    }
}
