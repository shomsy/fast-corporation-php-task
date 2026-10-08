<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Accounts;

use InterviewTask\Accounts\Login\InvalidCredentials;
use InterviewTask\Accounts\Login\Login;
use InterviewTask\Accounts\Register\Register;
use InterviewTask\Accounts\Role;
use InterviewTask\Accounts\SessionIdentity;

final class LoginTest extends AccountsTestCase
{
    public function testValidCredentialsRegenerateSessionAndEstablishIdentity(): void
    {
        $account = (new Register(accounts: $this->accounts, clock: $this->clock))->register(username: 'alice', password: 'correct horse battery staple');

        $session = $this->session();
        $session->start();
        $before = session_id();

        $identity = new SessionIdentity(session: $session, accounts: $this->accounts);
        $signedIn = (new Login(accounts: $this->accounts, identity: $identity))->login(username: 'alice', password: 'correct horse battery staple');

        self::assertNotSame($before, session_id());
        self::assertSame($account->id, $signedIn->id);
        self::assertSame('alice', $signedIn->username);
        self::assertSame(Role::User, $signedIn->role);
        self::assertEquals($signedIn, $identity->current());
    }

    public function testUnknownUserAndWrongPasswordExposeSameFailure(): void
    {
        (new Register(accounts: $this->accounts, clock: $this->clock))->register(username: 'alice', password: 'correct horse battery staple');

        foreach (
            [
                ['missing', 'correct horse battery staple'],
                ['alice', 'wrong password'],
            ] as [$username, $password]
        ) {
            $identity = new SessionIdentity(session: $this->session(), accounts: $this->accounts);
            $login    = new Login(accounts: $this->accounts, identity: $identity);

            try {
                $login->login(username: $username, password: $password);
                self::fail('Expected invalid credentials.');
            } catch (InvalidCredentials $exception) {
                self::assertSame('Invalid username or password.', $exception->getMessage());
                self::assertNull($identity->current());
            }
        }
    }
}
