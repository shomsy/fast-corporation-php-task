<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Accounts;

use InterviewTask\Accounts\AccessDenied;
use InterviewTask\Accounts\CreateAdmin\CreateAdmin;
use InterviewTask\Accounts\Register\Register;
use InterviewTask\Accounts\RequireAdmin;
use InterviewTask\Accounts\RequireSignedIn;
use InterviewTask\Accounts\Role;
use InterviewTask\Accounts\SessionIdentity;

final class AuthorizationTest extends AccountsTestCase
{
    public function testSignedInGuardRejectsAnonymousAndReturnsAuthenticatedAccount(): void
    {
        $identity = new SessionIdentity($this->session(), $this->accounts);
        $guard = new RequireSignedIn($identity);

        try {
            $guard->account();
            self::fail('Anonymous access should be denied.');
        } catch (AccessDenied) {
            self::assertNull($identity->current());
        }

        $account = (new Register($this->accounts, $this->clock))->register('alice', 'password');
        $identity->signIn($account);

        self::assertSame($account->id, $guard->account()->id);
    }

    public function testAdminGuardRejectsUserAndAcceptsAdmin(): void
    {
        $session = $this->session();
        $identity = new SessionIdentity($session, $this->accounts);
        $guard = new RequireAdmin($identity);

        $user = (new Register($this->accounts, $this->clock))->register('alice', 'password');
        $identity->signIn($user);

        try {
            $guard->account();
            self::fail('Ordinary user must not pass admin authorization.');
        } catch (AccessDenied) {
            self::assertSame(Role::User, $identity->current()?->role);
        }

        $identity->signOut();

        $admin = (new CreateAdmin($this->accounts, $this->clock))->create('admin', 'password');
        $identity->signIn($admin);

        self::assertSame(Role::Admin, $guard->account()->role);
    }
}
