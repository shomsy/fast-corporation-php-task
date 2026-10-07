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
        $account = (new Register($this->accounts, $this->clock))->register('alice', 'correct horse battery staple');

        $session = $this->session();
        $session->start();
        $before = session_id();

        $identity = new SessionIdentity($session, $this->accounts);
        $signedIn = (new Login($this->accounts, $identity))->login('alice', 'correct horse battery staple');

        self::assertNotSame($before, session_id());
        self::assertSame($account->id, $signedIn->id);
        self::assertSame('alice', $signedIn->username);
        self::assertSame(Role::User, $signedIn->role);
        self::assertEquals($signedIn, $identity->current());
    }

    public function testUnknownUserAndWrongPasswordExposeSameFailure(): void
    {
        (new Register($this->accounts, $this->clock))->register('alice', 'correct horse battery staple');

        foreach (
            [
                ['missing', 'correct horse battery staple'],
                ['alice', 'wrong password'],
            ] as [$username, $password]
        ) {
            $identity = new SessionIdentity($this->session(), $this->accounts);
            $login = new Login($this->accounts, $identity);

            try {
                $login->login($username, $password);
                self::fail('Expected invalid credentials.');
            } catch (InvalidCredentials $exception) {
                self::assertSame('Invalid username or password.', $exception->getMessage());
                self::assertNull($identity->current());
            }
        }
    }
}
