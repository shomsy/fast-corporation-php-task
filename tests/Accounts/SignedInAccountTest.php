<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Accounts;

use InterviewTask\Accounts\Role;
use InterviewTask\Accounts\Access\SignedInAccount;
use PHPUnit\Framework\TestCase;
use ValueError;

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
        self::assertFalse((new SignedInAccount(id: 1, username: 'user', role: Role::User->value))->isAdmin());
        self::assertTrue((new SignedInAccount(id: 2, username: 'admin', role: Role::Admin->value))->isAdmin());
        self::assertSame('admin', (new SignedInAccount(id: 2, username: 'admin', role: 'admin'))->roleCode());
    }

    /**
     * Reject undefined role strings at the exported identity construction boundary.
     *
     * @return void No return value.
     */
    public function testRejectsUnrecognizedRoleCode() : void
    {
        $this->expectException(ValueError::class);
        new SignedInAccount(id: 3, username: 'alice', role: 'owner');
    }


}
