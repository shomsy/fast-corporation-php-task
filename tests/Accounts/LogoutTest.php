<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Accounts;

use InterviewTask\Accounts\Register\Register;
use InterviewTask\Accounts\SessionIdentity;

final class LogoutTest extends AccountsTestCase
{
    public function testSessionSignOutDestroysAuthenticatedSessionAndIdentity(): void
    {
        $account  = (new Register(accounts: $this->accounts, clock: $this->clock))->register(username: 'alice', password: 'password');
        $identity = new SessionIdentity(session: $this->session(), accounts: $this->accounts);
        $identity->signIn(account: $account);

        self::assertNotNull($identity->current());

        $identity->signOut();

        self::assertNotSame(PHP_SESSION_ACTIVE, session_status());
        self::assertSame([], $_SESSION);

        self::assertNull($identity->current());
    }
}
