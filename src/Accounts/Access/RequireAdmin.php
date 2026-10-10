<?php

declare(strict_types=1);

namespace InterviewTask\Accounts\Access;

use Throwable;

/**
 * Explicit authorization boundary for administrator-only behavior.
 *
 * @api
 * @package InterviewTask\Accounts\Access
 */
final readonly class RequireAdmin
{
    /**
     * Use server-side identity verification for authorization.
     *
     * @param SessionIdentity $identity Server-side authenticated identity access boundary.
     */
    public function __construct(private SessionIdentity $identity) {}

    /**
     * Return an administrator account or raise a denied-access outcome.
     *
     * @return SignedInAccount Authorized administrator identity.
     * @throws AccessDenied When the current identity lacks the required permission.
     * @throws Throwable When authoritative identity verification fails; access is never granted on failure.
     */
    public function account() : SignedInAccount
    {
        $account = $this->identity->current();

        if ($account === null || ! $account->isAdmin()) {
            throw new AccessDenied(message: 'Administrator access required.');
        }

        return $account;
    }
}
