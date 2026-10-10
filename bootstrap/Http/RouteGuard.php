<?php

declare(strict_types=1);

namespace InterviewTask\Bootstrap\Http;

use InterviewTask\Accounts\Access\CurrentAccount;
use JsonException;
use Random\RandomException;
use RuntimeException;
use Throwable;
use InterviewTask\Infrastructure\Diagnostics\SecurityEvents;
use InterviewTask\Infrastructure\Session\Csrf;
use InterviewTask\Runtime\Http\Request;
use InterviewTask\Runtime\Http\Response;
use InterviewTask\Runtime\Http\RouteAccess;

/**
 * The HTTP authorization and CSRF policy; route assembly does not decide access.
 *
 * @package InterviewTask\Bootstrap\Http
 */
final readonly class RouteGuard
{
    /**
     * @param CurrentAccount $account Only the server-side signed-in account can authorize.
     * @param Csrf           $csrf    Server-owned anti-CSRF policy.
     */
    public function __construct(private CurrentAccount $account, private Csrf $csrf) {}

    /**
     * Deny by policy before a route handler can execute.
     *
     * @param Request     $request Untrusted incoming request.
     * @param RouteAccess $access  Explicit route access requirement.
     *
     * @return Response|null Safe rejection, or null if authorized.
     * @throws JsonException When an API rejection cannot be serialized.
     * @throws RandomException When session verification cannot obtain CSRF entropy.
     * @throws RuntimeException When session or diagnostic access fails.
     * @throws Throwable When authoritative identity verification fails.
     */
    public function __invoke(Request $request, RouteAccess $access) : Response|null
    {
        if ($access !== RouteAccess::Public) {
            $identity = $this->account->current();

            if ($identity === null) {
                return str_starts_with(haystack: $request->path(), needle: '/api/')
                    ? Response::json(payload: ['error' => ['code' => 'unauthenticated', 'message' => 'Sign in required.']], status: 401)
                    : Response::redirect(location: '/login');
            }

            if ($access === RouteAccess::Admin && ! $identity->isAdmin()) {
                $denied = str_starts_with(haystack: $request->path(), needle: '/api/')
                    ? Response::json(payload: ['error' => ['code' => 'forbidden', 'message' => 'Forbidden.']], status: 403)
                    : Response::text(body: 'Forbidden', status: 403);

                return $denied->withHeader(name: 'X-Request-ID', value: SecurityEvents::record(event: 'security.admin_denied'));
            }
        }

        if (! in_array(needle: $request->method(), haystack: ['GET', 'HEAD', 'OPTIONS'], strict: true)
            && ! $this->csrf->matches(submitted: $request->csrfToken())) {
            $denied = str_starts_with(haystack: $request->path(), needle: '/api/')
                ? Response::json(payload: ['error' => ['code' => 'csrf_denied', 'message' => 'Session verification failed.']], status: 403)
                : Response::text(body: 'Forbidden', status: 403);

            return $denied->withHeader(name: 'X-Request-ID', value: SecurityEvents::record(event: 'security.csrf_denied'));
        }

        return null;
    }
}
