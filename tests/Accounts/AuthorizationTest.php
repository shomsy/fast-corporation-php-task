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

/**
 * Regression checks for Authorization test, covering observable behavior and failure paths.
 *
 * @package InterviewTask\Tests\Accounts
 */
final class AuthorizationTest extends AccountsTestCase
{
    /**
     * Verify signed in guard rejects anonymous and returns authenticated account through the public behavior.
     *
     * @return void No return value.
     */
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

        $account = (new Register(accounts: $this->accounts, clock: $this->clock))->register(username: 'alice', password: 'strong-password');
        $identity->signIn(account: $account);

        self::assertSame($account->id, $guard->account()->id);
    }

    /**
     * Verify admin guard rejects user and accepts admin through the public behavior.
     *
     * @return void No return value.
     */
    public function testAdminGuardRejectsUserAndAcceptsAdmin(): void
    {
        $session  = $this->session();
        $identity = new SessionIdentity(session: $session, accounts: $this->accounts);
        $guard    = new RequireAdmin(identity: $identity);

        $user = (new Register(accounts: $this->accounts, clock: $this->clock))->register(username: 'alice', password: 'strong-password');
        $identity->signIn(account: $user);

        try {
            $guard->account();
            self::fail('Ordinary user must not pass admin authorization.');
        } catch (AccessDenied) {
            self::assertSame(Role::User, $identity->current()?->role);
        }

        $identity->signOut();

        $admin = (new CreateAdmin(accounts: $this->accounts, clock: $this->clock))->create(username: 'admin', password: 'strong-password');
        $identity->signIn(account: $admin);

        self::assertSame(Role::Admin, $guard->account()->role);
    }

    /**
     * Verify revoked admin role immediately fails authorization with existing session through the public behavior.
     *
     * @return void No return value.
     */
    public function testRevokedAdminRoleImmediatelyFailsAuthorizationWithExistingSession(): void
    {
        $identity = new SessionIdentity(session: $this->session(), accounts: $this->accounts, clock: $this->clock);
        $guard    = new RequireAdmin(identity: $identity);
        $admin    = (new CreateAdmin(accounts: $this->accounts, clock: $this->clock))->create(username: 'admin', password: 'strong-password');
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
