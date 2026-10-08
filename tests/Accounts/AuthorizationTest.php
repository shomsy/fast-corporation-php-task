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
        $identity = new SessionIdentity(session: $this->session(), accounts: $this->accounts);
        $guard    = new RequireSignedIn(identity: $identity);

        try {
            $guard->account();
            self::fail('Anonymous access should be denied.');
        } catch (AccessDenied) {
            self::assertNull($identity->current());
        }

        $account = (new Register(accounts: $this->accounts, clock: $this->clock))->register(username: 'alice', password: 'password');
        $identity->signIn(account: $account);

        self::assertSame($account->id, $guard->account()->id);
    }

    public function testAdminGuardRejectsUserAndAcceptsAdmin(): void
    {
        $session  = $this->session();
        $identity = new SessionIdentity(session: $session, accounts: $this->accounts);
        $guard    = new RequireAdmin(identity: $identity);

        $user = (new Register(accounts: $this->accounts, clock: $this->clock))->register(username: 'alice', password: 'password');
        $identity->signIn(account: $user);

        try {
            $guard->account();
            self::fail('Ordinary user must not pass admin authorization.');
        } catch (AccessDenied) {
            self::assertSame(Role::User, $identity->current()?->role);
        }

        $identity->signOut();

        $admin = (new CreateAdmin(accounts: $this->accounts, clock: $this->clock))->create(username: 'admin', password: 'password');
        $identity->signIn(account: $admin);

        self::assertSame(Role::Admin, $guard->account()->role);
    }

    public function testRevokedAdminRoleImmediatelyFailsAuthorizationWithExistingSession(): void
    {
        $identity = new SessionIdentity(session: $this->session(), accounts: $this->accounts, clock: $this->clock);
        $guard    = new RequireAdmin(identity: $identity);
        $admin    = (new CreateAdmin(accounts: $this->accounts, clock: $this->clock))->create(username: 'admin', password: 'password');
        $identity->signIn(account: $admin);
        self::assertSame(Role::Admin, $guard->account()->role);

        $update = $this->pdo->prepare(query: 'UPDATE accounts SET role = :role WHERE id = :id');
        self::assertNotFalse($update);
        self::assertTrue($update->execute(['role' => Role::User->value, 'id' => $admin->id]));

        self::assertSame(Role::User, $identity->current()?->role);
        $this->expectException(AccessDenied::class);
        $guard->account();
    }
}
