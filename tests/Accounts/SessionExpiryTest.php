<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Accounts;

use InterviewTask\Accounts\Register\Register;
use InterviewTask\Accounts\SessionIdentity;
use InterviewTask\Infrastructure\Session\PhpSession;

final class SessionExpiryTest extends AccountsTestCase
{
    public function testStrictModeAndSignOutDestroySession(): void
    {
        $session = $this->session();
        $session->start();
        self::assertSame('1', ini_get(option: 'session.use_strict_mode'));

        $account  = (new Register(accounts: $this->accounts, clock: $this->clock))->register(username: 'alice', password: 'valid-password');
        $identity = new SessionIdentity(session: $session, accounts: $this->accounts, clock: $this->clock);
        $identity->signIn(account: $account);
        self::assertNotNull($identity->current());

        $identity->signOut();
        self::assertNull($identity->current());
    }

    public function testSignInRotatesTheAnonymousSessionIdentifier(): void
    {
        $session = $this->session();
        $session->start();
        $anonymousId = session_id();
        self::assertNotSame('', $anonymousId);

        $account  = (new Register(accounts: $this->accounts, clock: $this->clock))->register(username: 'alice', password: 'valid-password');
        $identity = new SessionIdentity(session: $session, accounts: $this->accounts, clock: $this->clock);
        $identity->signIn(account: $account);

        self::assertNotSame($anonymousId, session_id());
        self::assertSame('alice', $identity->current()?->username);
    }

    public function testAttackerProvidedUnknownSessionIdIsRejected(): void
    {
        $attackerId = str_repeat(string: 'a', times: 32);
        session_id(id: $attackerId);
        $this->session()->start();

        self::assertSame('1', ini_get(option: 'session.use_strict_mode'));
        self::assertNotSame($attackerId, session_id());
        self::assertSame([], $_SESSION);
    }

    public function testCookieConfigurationRequiresSecureHttpOnlyAndSameSiteLax(): void
    {
        ini_set(option: 'session.use_cookies', value: '1');
        $session = new PhpSession(name: 'secure_cookie_test', secureCookie: true);
        $session->start();

        $params = session_get_cookie_params();
        self::assertTrue($params['secure']);
        self::assertTrue($params['httponly']);
        self::assertSame('Lax', $params['samesite']);
        self::assertSame('/', $params['path']);
    }

    public function testIdleTimeoutRevokesPreviouslyValidAuthentication(): void
    {
        $account  = (new Register(accounts: $this->accounts, clock: $this->clock))->register(username: 'alice', password: 'valid-password');
        $identity = new SessionIdentity(session: $this->session(), accounts: $this->accounts, clock: $this->clock);
        $identity->signIn(account: $account);

        $_SESSION['last_activity_at'] = $this->clock->now()->getTimestamp() - 1800;

        self::assertNull($identity->current());
        self::assertSame([], $_SESSION);
    }

    public function testAbsoluteTimeoutRevokesSessionEvenWhenRecentlyActive(): void
    {
        $account  = (new Register(accounts: $this->accounts, clock: $this->clock))->register(username: 'alice', password: 'valid-password');
        $identity = new SessionIdentity(session: $this->session(), accounts: $this->accounts, clock: $this->clock);
        $identity->signIn(account: $account);
        $_SESSION['authenticated_at'] = $this->clock->now()->getTimestamp() - 43200;
        $_SESSION['last_activity_at'] = $this->clock->now()->getTimestamp() - 1;

        self::assertNull($identity->current());
    }

    public function testUnknownSessionMetadataDoesNotAuthenticate(): void
    {
        $account  = (new Register(accounts: $this->accounts, clock: $this->clock))->register(username: 'alice', password: 'valid-password');
        $identity = new SessionIdentity(session: new PhpSession(name: 'expired_session_test', secureCookie: false), accounts: $this->accounts, clock: $this->clock);
        $identity->signIn(account: $account);
        unset($_SESSION['authenticated_at']);

        self::assertNull($identity->current());
    }
}
