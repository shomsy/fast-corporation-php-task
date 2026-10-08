<?php

declare(strict_types=1);

namespace InterviewTask\Accounts;

use InterviewTask\Infrastructure\Session\PhpSession;
use InterviewTask\Infrastructure\Time\SystemClock;
use Psr\Clock\ClockInterface;

/**
 * Authoritative server-session identity lifecycle, with idle and absolute expiry. Store only the account ID and timestamps in session state; never trust a client role.
 *
 * @package InterviewTask\Accounts
 */
final readonly class SessionIdentity
{
    /**
     * Session key for the current authenticated account ID.
     *
     * @var string
     */
    private const string ACCOUNT_ID           = 'account_id';
    /**
     * Session key for initial authentication time.
     *
     * @var string
     */
    private const string ISSUED_AT            = 'authenticated_at';
    /**
     * Session key for latest authorized activity time.
     *
     * @var string
     */
    private const string LAST_SEEN_AT         = 'last_activity_at';
    /**
     * Maximum idle period before the server session expires.
     *
     * @var int
     */
    private const int    MAX_IDLE_SECONDS     = 1800;
    /**
     * Absolute maximum lifetime of a signed-in session.
     *
     * @var int
     */
    private const int    MAX_LIFETIME_SECONDS = 43200;

    /**
     * Configure the session adapter, account reader, and optional test clock.
     *
     * @param PhpSession $session Trusted server-side PHP session adapter.
     * @param Accounts $accounts Authoritative Accounts owner persistence API.
     * @param ClockInterface|null $clock Wall-clock source used for deterministic timestamps.
     */
    public function __construct(
        private PhpSession      $session,
        private Accounts        $accounts,
        private ?ClockInterface $clock = null,
    ) {}

    /**
     * Rotate the session identifier before publishing an authenticated identity.
     *
     * @param Account $account Immutable account from authoritative storage.
     * @return SignedInAccount The new immutable signed-in account identity.
     */
    public function signIn(Account $account): SignedInAccount
    {
        $this->session->regenerate();
        $this->session->put(key: self::ACCOUNT_ID, value: $account->id);
        $now = $this->now();
        $this->session->put(key: self::ISSUED_AT, value: $now);
        $this->session->put(key: self::LAST_SEEN_AT, value: $now);

        return SignedInAccount::fromAccount(account: $account);
    }

    /**
     * Read the current timestamp from the configured clock.
     *
     * @return int Current wall-clock timestamp in seconds.
     */
    private function now(): int
    {
        return ($this->clock ?? new SystemClock())->now()->getTimestamp();
    }

    /**
     * Verify both expirations and reload account/role from the database on access. Expired or invalid sessions are discarded; missing accounts are deauthenticated.
     *
     * @return SignedInAccount|null Verified current identity, or null after expiry or removal.
     */
    public function current(): ?SignedInAccount
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

        return SignedInAccount::fromAccount(account: $account);
    }

    /**
     * Destroy server-side authentication state, including its CSRF token.
     *
     * @return void No return value.
     */
    public function signOut(): void
    {
        $this->session->destroy();
    }
}
