<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Accounts;

use DateTimeImmutable;
use InterviewTask\Accounts\Login\Login;
use InterviewTask\Accounts\Register\Register;
use InterviewTask\Accounts\SessionIdentity;
use InterviewTask\Infrastructure\Session\Csrf;
use Override;
use Psr\Clock\ClockInterface;
use RuntimeException;

/**
 * Authentication cannot publish a different account when prerequisite initialization fails.
 *
 * @package InterviewTask\Tests\Accounts
 */
final class SessionInitializationTest extends AccountsTestCase
{
    /**
     * A failed clock preserves the existing account, session identifier, expiry and CSRF token.
     *
     * @return void No return value.
     */
    public function testFailedLoginCannotSwitchAnExistingIdentity() : void
    {
        $register = new Register(accounts: $this->accounts, clock: $this->clock);
        $original = $register->register(username: 'original', password: 'strong-password');
        $register->register(username: 'replacement', password: 'strong-password');
        $session  = $this->session();
        $identity = new SessionIdentity(session: $session, accounts: $this->accounts, clock: $this->clock, cleanupFailures: $this->cleanupFailures);
        $identity->signIn(account: $original);
        $before      = $_SESSION;
        $sessionId   = session_id();
        $csrf        = new Csrf(session: $session);
        $token       = $csrf->token();
        $failure     = new RuntimeException(message: 'Clock is unavailable.');
        $unavailable = new SessionIdentity(session: $session, accounts: $this->accounts, clock: $this->failingClock(failure: $failure), cleanupFailures: $this->cleanupFailures);

        try {
            (new Login(accounts: $this->accounts, identity: $unavailable))->login(username: 'replacement', password: 'strong-password');
            self::fail('The clock failure must propagate.');
        } catch (RuntimeException $error) {
            self::assertSame($failure, $error);
        }

        self::assertSame($sessionId, session_id());
        self::assertSame($before, $_SESSION);
        self::assertTrue($csrf->matches(submitted: $token));
        self::assertSame($original->id, $identity->current()?->id);
    }

    /**
     * Anonymous session data must also stay untouched on failed initialization.
     *
     * @return void No return value.
     */
    public function testFailedLoginDoesNotModifyAnAnonymousSession() : void
    {
        $account   = (new Register(accounts: $this->accounts, clock: $this->clock))->register(username: 'alice', password: 'strong-password');
        $session   = $this->session();
        $token     = (new Csrf(session: $session))->token();
        $before    = $_SESSION;
        $sessionId = session_id();
        $failure   = new RuntimeException(message: 'Clock is unavailable.');
        $identity  = new SessionIdentity(session: $session, accounts: $this->accounts, clock: $this->failingClock(failure: $failure), cleanupFailures: $this->cleanupFailures);

        try {
            $identity->signIn(account: $account);
            self::fail('The clock failure must propagate.');
        } catch (RuntimeException $error) {
            self::assertSame($failure, $error);
        }

        self::assertSame($sessionId, session_id());
        self::assertSame($before, $_SESSION);
        self::assertTrue((new Csrf(session: $session))->matches(submitted: $token));
    }

    /**
     * Supply a replaceable time source that preserves the exact operational error.
     *
     * @param RuntimeException $failure Original clock failure.
     *
     * @return ClockInterface Failing time source.
     */
    private function failingClock(RuntimeException $failure) : ClockInterface
    {
        return new class($failure) implements ClockInterface {
            /**
             * @param RuntimeException $failure Original clock failure.
             */
            public function __construct(private RuntimeException $failure) {}

            /**
             * Fail before any authentication data is published.
             *
             * @return DateTimeImmutable No time can be supplied.
             * @throws RuntimeException When the clock is unavailable.
             */
            #[Override]
            public function now() : DateTimeImmutable
            {
                throw $this->failure;
            }
        };
    }
}
