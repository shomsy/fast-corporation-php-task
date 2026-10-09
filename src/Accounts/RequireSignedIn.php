<?php

declare(strict_types=1);

namespace InterviewTask\Accounts;

/**
 * Explicit authorization boundary for authenticated business behavior.
 *
 * @api
 * @package InterviewTask\Accounts
 */
final readonly class RequireSignedIn
{
    /**
     * Use server-side identity verification for authorization.
     *
     * @param SessionIdentity $identity Server-side authenticated identity access boundary.
     */
    public function __construct(private SessionIdentity $identity) {}

    /**
     * Return the authenticated account or raise a denied-access outcome.
     *
     * @return SignedInAccount Authorized signed-in account identity.
     * @throws AccessDenied When the current identity lacks the required permission.
     */
    public function account() : SignedInAccount
    {
        return $this->identity->current()
            ?? throw new AccessDenied(message: 'Authentication required.');
    }
}
