<?php

declare(strict_types=1);

namespace InterviewTask\Accounts;

/**
 * Safe cross-owner view of authentication. Session mutation belongs exclusively to the owner-internal lifecycle implementation.
 *
 * @api
 * @package InterviewTask\Accounts
 */
final readonly class CurrentAccount
{
    /**
     * Expose identity reads without exposing session mutation to consumers.
     *
     * @param SessionIdentity $identity Server-side authenticated identity access boundary.
     */
    public function __construct(private SessionIdentity $identity) {}

    /**
     * Query the current identity without exposing the session mutation API.
     *
     * @return SignedInAccount|null Current verified identity, or null when not signed in.
     */
    public function current(): SignedInAccount|null
    {
        return $this->identity->current();
    }
}
