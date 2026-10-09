<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Accounts;

use InterviewTask\Accounts\Register\Register;
use InterviewTask\Accounts\SessionIdentity;
use InterviewTask\Infrastructure\Session\PhpSession;
use InterviewTask\Infrastructure\Session\Csrf;

/**
 * Regression checks for Session expiry test, covering observable behavior and failure paths.
 *
 * @package InterviewTask\Tests\Accounts
 */
final class SessionExpiryTest extends AccountsTestCase
{
    /**
     * Verify strict mode and sign out destroy session through the public behavior.
     *
     * @return void No return value.
     */
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

    /**
     * Verify sign in rotates the anonymous session identifier through the public behavior.
     *
     * @return void No return value.
     */
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

    /**
     * Verify attacker provided unknown session id is rejected through the public behavior.
     *
     * @return void No return value.
     */
    public function testAttackerProvidedUnknownSessionIdIsRejected(): void
    {
        $attackerId = str_repeat(string: 'a', times: 32);
        session_id(id: $attackerId);
        $this->session()->start();

        self::assertSame('1', ini_get(option: 'session.use_strict_mode'));
        self::assertNotSame($attackerId, session_id());
        self::assertSame([], $_SESSION);
    }

    /**
     * Verify cookie configuration requires secure http only and same site lax through the public behavior.
     *
     * @return void No return value.
     */
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

    /**
     * Native garbage collection must not discard a session before the 12-hour server-side limit.
     *
     * @return void No return value.
     */
    public function testNativeSessionGcCoversAbsoluteAuthenticationLifetime(): void
    {
        ini_set(option: 'session.gc_maxlifetime', value: '1440');

        $this->session()->start();

        self::assertGreaterThanOrEqual(43200, (int) ini_get(option: 'session.gc_maxlifetime'));
    }

    /**
     * A configured private session directory must be applied before native session startup.
     *
     * @return void No return value.
     */
    public function testNativeSessionUsesConfiguredPrivateStorage(): void
    {
        $path = sys_get_temp_dir() . '/fast-session-files-' . bin2hex(string: random_bytes(length: 8));
        self::assertTrue(mkdir(directory: $path, permissions: 0700));
        $oldPath = session_save_path();
        self::assertIsString($oldPath);

        try {
            (new PhpSession(name: 'isolated-storage-test', savePath: $path))->start();
            self::assertSame($path, session_save_path());
            self::assertGreaterThanOrEqual(43200, (int) ini_get(option: 'session.gc_maxlifetime'));

            $files = glob(pattern: $path . '/sess_*');
            self::assertIsArray($files);
            self::assertCount(1, $files);
        } finally {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }
            session_id(id: '');
            session_save_path(path: $oldPath);

            foreach (glob(pattern: $path . '/sess_*') ?: [] as $file) {
                unlink(filename: $file);
            }
            rmdir(directory: $path);
        }
    }

    /**
     * Verify idle timeout revokes previously valid authentication through the public behavior.
     *
     * @return void No return value.
     */
    public function testIdleTimeoutRevokesPreviouslyValidAuthentication(): void
    {
        $account  = (new Register(accounts: $this->accounts, clock: $this->clock))->register(username: 'alice', password: 'valid-password');
        $identity = new SessionIdentity(session: $this->session(), accounts: $this->accounts, clock: $this->clock);
        $identity->signIn(account: $account);

        $_SESSION['last_activity_at'] = $this->clock->now()->getTimestamp() - 1800;

        self::assertNull($identity->current());
        self::assertSame([], $_SESSION);
    }

    /**
     * Verify absolute timeout revokes session even when recently active through the public behavior.
     *
     * @return void No return value.
     */
    public function testAbsoluteTimeoutRevokesSessionEvenWhenRecentlyActive(): void
    {
        $account  = (new Register(accounts: $this->accounts, clock: $this->clock))->register(username: 'alice', password: 'valid-password');
        $identity = new SessionIdentity(session: $this->session(), accounts: $this->accounts, clock: $this->clock);
        $identity->signIn(account: $account);
        $_SESSION['authenticated_at'] = $this->clock->now()->getTimestamp() - 43200;
        $_SESSION['last_activity_at'] = $this->clock->now()->getTimestamp() - 1;

        self::assertNull($identity->current());
    }

    /**
     * Verify unknown session metadata does not authenticate through the public behavior.
     *
     * @return void No return value.
     */
    public function testUnknownSessionMetadataDoesNotAuthenticate(): void
    {
        $account  = (new Register(accounts: $this->accounts, clock: $this->clock))->register(username: 'alice', password: 'valid-password');
        $identity = new SessionIdentity(session: new PhpSession(name: 'expired_session_test', secureCookie: false), accounts: $this->accounts, clock: $this->clock);
        $identity->signIn(account: $account);
        unset($_SESSION['authenticated_at']);

        self::assertNull($identity->current());
    }

    /**
     * Login rotates both session ID and CSRF token; old anonymous form tokens are invalid.
     *
     * @return void No return value.
     */
    public function testLoginRotatesCsrfTokenAndInvalidatesAnonymousForms(): void
    {
        $session = $this->session();
        $csrf = new Csrf(session: $session);
        $oldSessionId = session_id();
        $oldToken = $csrf->token();

        $account = (new Register(accounts: $this->accounts, clock: $this->clock))
            ->register(username: 'alice', password: 'valid-password');
        $identity = new SessionIdentity(session: $session, accounts: $this->accounts, clock: $this->clock);
        $identity->signIn(account: $account);

        self::assertNotSame($oldSessionId, session_id());
        self::assertFalse($csrf->matches(submitted: $oldToken));
        $newToken = $csrf->token();
        self::assertNotSame($oldToken, $newToken);
        self::assertTrue($csrf->matches(submitted: $newToken));
        self::assertNotNull($identity->current());

        $identity->signOut();
        self::assertFalse($csrf->matches(submitted: $newToken));
    }

}
