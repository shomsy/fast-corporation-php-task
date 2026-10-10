<?php

declare(strict_types=1);

namespace InterviewTask\Bootstrap\Http;

use InterviewTask\Accounts\Access\CurrentAccount;
use InterviewTask\Infrastructure\Session\Csrf;
use InterviewTask\Runtime\Http\Response;
use JsonException;
use Random\RandomException;
use Throwable;

/**
 * Project existing server-owned session and CSRF state into minimal JSON transport.
 *
 * @package InterviewTask\Bootstrap\Http
 */
final readonly class SessionApi
{
    /**
     * Bind the authoritative account reader and the shared CSRF session service.
     *
     * @param CurrentAccount $identity Reloaded authoritative current account.
     * @param Csrf           $csrf     Server-side CSRF token lifecycle.
     */
    public function __construct(
        private CurrentAccount $identity,
        private Csrf           $csrf,
    ) {}

    /**
     * Resolve the current account without exporting session credentials or role authority.
     *
     * @return Response Public no-store JSON containing user identity or null.
     * @throws JsonException When identity serialization fails.
     * @throws Throwable When authoritative identity or session access fails.
     */
    public function session() : Response
    {
        $account = $this->identity->current();

        return Response::json(payload: [
                                           'user' => $account === null ? null : [
                                               'id'       => $account->id,
                                               'username' => $account->username,
                                               'role'     => $account->roleCode(),
                                           ],
                                       ]);
    }

    /**
     * Return a token valid only within the caller's server-side PHP session.
     *
     * @return Response Public no-store JSON containing the current CSRF token.
     * @throws JsonException When token serialization fails.
     * @throws RandomException When token generation fails.
     * @throws Throwable When native session access or token persistence fails.
     */
    public function csrf() : Response
    {
        return Response::json(payload: ['csrfToken' => $this->csrf->token()]);
    }
}
