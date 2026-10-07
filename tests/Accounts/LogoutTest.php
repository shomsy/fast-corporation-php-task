<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Accounts;

use InterviewTask\Accounts\Logout\Logout;
use InterviewTask\Accounts\Register\Register;
use InterviewTask\Accounts\SessionIdentity;

final class LogoutTest extends AccountsTestCase
{
    public function testLogoutDestroysAuthenticatedSessionAndIdentity(): void
    {
        $account = (new Register($this->accounts, $this->clock))->register('alice', 'password');
        $identity = new SessionIdentity($this->session(), $this->accounts);
        $identity->signIn($account);

        self::assertNotNull($identity->current());

        (new Logout($identity))->logout();

        self::assertNotSame(PHP_SESSION_ACTIVE, session_status());
        self::assertSame([], $_SESSION);

        self::assertNull($identity->current());
    }
}
