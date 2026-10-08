<?php

declare(strict_types=1);

namespace InterviewTask\Runtime\Http;

/**
 * Explicit route access policy; unannotated routes default to authenticated access.
 *
 * @package InterviewTask\Runtime\Http
 */
enum RouteAccess
{
    /**
     * A public route requires no account but unsafe requests still require CSRF.
     */
    case Public;

    /**
     * A signed-in route requires a server-verified account.
     */
    case SignedIn;

    /**
     * An administrator route requires a server-verified admin account.
     */
    case Admin;
}
