<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Accounts;

use InterviewTask\Accounts\Register\Register;
use InterviewTask\Accounts\Access\SessionIdentity;

/**
 * Regression checks for Logout test, covering observable behavior and failure paths.
 *
 * @package InterviewTask\Tests\Accounts
 */
final class LogoutTest extends AccountsTestCase
{
    /**
     * Verify session sign out destroys authenticated session and identity through the public behavior.
     *
     * @return void No return value.
     */
    public function testSessionSignOutDestroysAuthenticatedSessionAndIdentity() : void
    {
        $account  = (new Register(accounts: $this->accounts, clock: $this->clock))->register(username: 'alice', password: 'strong-password');
        $identity = new SessionIdentity(session: $this->session(), accounts: $this->accounts, clock: $this->clock, cleanupFailures: $this->cleanupFailures);
        $identity->signIn(account: $account);

        self::assertNotNull($identity->current());

        $identity->signOut();

        self::assertNotSame(PHP_SESSION_ACTIVE, session_status());
        self::assertSame([], $_SESSION);

        self::assertNull($identity->current());
    }
}
