<?php

declare(strict_types=1);

namespace InterviewTask\Accounts;

use InterviewTask\Infrastructure\Session\PhpSession;
use InterviewTask\Infrastructure\Time\SystemClock;
use Psr\Clock\ClockInterface;

final readonly class SessionIdentity
{
    private const string ACCOUNT_ID           = 'account_id';
    private const string ISSUED_AT            = 'authenticated_at';
    private const string LAST_SEEN_AT         = 'last_activity_at';
    private const int    MAX_IDLE_SECONDS     = 1800;
    private const int    MAX_LIFETIME_SECONDS = 43200;

    public function __construct(
        private PhpSession      $session,
        private Accounts        $accounts,
        private ?ClockInterface $clock = null,
    ) {}

    public function signIn(Account $account) : SignedInAccount
    {
        $this->session->regenerate();
        $this->session->put(self::ACCOUNT_ID, $account->id);
        $now = $this->now();
        $this->session->put(self::ISSUED_AT, $now);
        $this->session->put(self::LAST_SEEN_AT, $now);

        return SignedInAccount::fromAccount($account);
    }

    private function now() : int
    {
        return ($this->clock ?? new SystemClock())->now()->getTimestamp();
    }

    public function current() : ?SignedInAccount
    {
        $id = $this->session->get(self::ACCOUNT_ID);

        if (! is_int($id)) {
            return null;
        }

        $issuedAt = $this->session->get(self::ISSUED_AT);
        $lastSeen = $this->session->get(self::LAST_SEEN_AT);
        $now      = $this->now();

        if (! is_int($issuedAt) || ! is_int($lastSeen)
            || $issuedAt > $now || $lastSeen > $now || $lastSeen < $issuedAt
            || $now - $issuedAt >= self::MAX_LIFETIME_SECONDS
            || $now - $lastSeen >= self::MAX_IDLE_SECONDS
        ) {
            $this->session->destroy();

            return null;
        }

        $account = $this->accounts->findById($id);

        if ($account === null) {
            $this->session->remove(self::ACCOUNT_ID);

            return null;
        }

        $this->session->put(self::LAST_SEEN_AT, $now);

        return SignedInAccount::fromAccount($account);
    }

    public function signOut() : void
    {
        $this->session->destroy();
    }
}
