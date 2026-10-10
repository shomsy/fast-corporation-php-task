<?php

declare(strict_types=1);

namespace InterviewTask\Accounts\Access;

use InterviewTask\Accounts\Account;
use InterviewTask\Accounts\Accounts;

use InterviewTask\Infrastructure\Session\PhpSession;
use InterviewTask\Infrastructure\Session\Csrf;
use InterviewTask\Infrastructure\Diagnostics\CleanupFailures;
use PDOException;
use Psr\Clock\ClockInterface;
use Random\RandomException;
use RuntimeException;
use Throwable;

/**
 * Authoritative server-session identity lifecycle, with idle and absolute expiry. Store only the account ID and
 * timestamps in session state; never trust a client role.
 *
 * @package InterviewTask\Accounts\Access
 */
final readonly class SessionIdentity
{
    /**
     * Session key for the current authenticated account ID.
     *
     * @var string
     */
    private const string ACCOUNT_ID = 'account_id';
    /**
     * Session key for initial authentication time.
     *
     * @var string
     */
    private const string ISSUED_AT = 'authenticated_at';
    /**
     * Session key for latest authorized activity time.
     *
     * @var string
     */
    private const string LAST_SEEN_AT = 'last_activity_at';
    /**
     * Maximum idle period before the server session expires.
     *
     * @var int
     */
    private const int    MAX_IDLE_SECONDS = 1800;
    /**
     * Absolute maximum lifetime of a signed-in session.
     *
     * @var int
     */
    private const int    MAX_LIFETIME_SECONDS = 43200;

    /**
     * Configure the session adapter, authoritative account reader, and required clock.
     *
     * @param PhpSession      $session         Trusted server-side PHP session adapter.
     * @param Accounts        $accounts        Authoritative Accounts owner persistence API.
     * @param ClockInterface  $clock           Mandatory time source for authentication expiration.
     * @param CleanupFailures $cleanupFailures Best-effort reporting when failed establishment also fails revocation.
     */
    public function __construct(
        private PhpSession      $session,
        private Accounts        $accounts,
        private ClockInterface  $clock,
        private CleanupFailures $cleanupFailures,
    ) {}

    /**
     * Rotate and persist the authenticated session before the caller can accept the login.
     *
     * @param Account $account Immutable account from authoritative storage.
     *
     * @return SignedInAccount The new immutable signed-in account identity.
     * @throws RuntimeException When native session startup, rotation, persistence or reopening fails.
     * @throws RandomException When the new CSRF token cannot be generated.
     * @throws Throwable When the clock fails before mutation, or a session mechanism fails and revocation is attempted.
     */
    public function signIn(Account $account) : SignedInAccount
    {
        $now = $this->now();
        try {
            $this->session->regenerate();
            // A pre-login anonymous form must not authorize a privileged session.
            (new Csrf(session: $this->session))->rotate();
            $this->session->put(key: self::ACCOUNT_ID, value: $account->id);
            $this->session->put(key: self::ISSUED_AT, value: $now);
            $this->session->put(key: self::LAST_SEEN_AT, value: $now);
            $this->session->persist();
        } catch (Throwable $error) {
            try {
                $this->signOut();
            } catch (Throwable $cleanupError) {
                $this->cleanupFailures->record(error: $cleanupError);
            }

            throw $error;
        }

        return new SignedInAccount(id: $account->id, username: $account->username, role: $account->role->value);
    }

    /**
     * Read the current timestamp from the configured clock.
     *
     * @return int Current wall-clock timestamp in seconds.
     */
    private function now() : int
    {
        return $this->clock->now()->getTimestamp();
    }

    /**
     * Verify both expirations and reload account/role from the database on access. Expired or invalid sessions are
     * discarded; missing accounts are deauthenticated.
     *
     * @return SignedInAccount|null Verified current identity, or null after expiry or removal.
     * @throws PDOException When authoritative account lookup fails.
     * @throws RuntimeException When session access, cleanup or stored account decoding fails.
     * @throws Throwable When the configured clock or another operational dependency fails.
     */
    public function current() : SignedInAccount|null
    {
        $id = $this->session->get(key: self::ACCOUNT_ID);

        if (! is_int(value: $id)) {
            return null;
        }

        $issuedAt = $this->session->get(key: self::ISSUED_AT);
        $lastSeen = $this->session->get(key: self::LAST_SEEN_AT);
        $now      = $this->now();

        if (! is_int(value: $issuedAt) || ! is_int(value: $lastSeen)
            || $issuedAt > $now || $lastSeen > $now || $lastSeen < $issuedAt
            || $now - $issuedAt >= self::MAX_LIFETIME_SECONDS
            || $now - $lastSeen >= self::MAX_IDLE_SECONDS
        ) {
            $this->session->destroy();

            return null;
        }

        $account = $this->accounts->findById(id: $id);

        if ($account === null) {
            $this->session->remove(key: self::ACCOUNT_ID);

            return null;
        }

        $this->session->put(key: self::LAST_SEEN_AT, value: $now);

        return new SignedInAccount(id: $account->id, username: $account->username, role: $account->role->value);
    }

    /**
     * Destroy server-side authentication state, including its CSRF token.
     *
     * @return void No return value.
     * @throws RuntimeException When native session reopening or destruction fails; in-memory identity is cleared.
     */
    public function signOut() : void
    {
        $this->session->destroy();
    }
}
