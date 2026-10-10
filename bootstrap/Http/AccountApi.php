<?php

declare(strict_types=1);

namespace InterviewTask\Bootstrap\Http;

use InterviewTask\Accounts\AccountLifecycle;
use InterviewTask\Accounts\Login\InvalidCredentials;
use InterviewTask\Accounts\Register\RegistrationRejected;
use InterviewTask\Accounts\UsernameTaken;
use InterviewTask\Infrastructure\Diagnostics\SecurityEvents;
use InterviewTask\Infrastructure\Session\Csrf;
use InterviewTask\Runtime\Http\Request;
use InterviewTask\Runtime\Http\Response;
use InvalidArgumentException;
use JsonException;
use Random\RandomException;
use Throwable;

/**
 * JSON Accounts transport, sharing the SSR owner's authoritative lifecycle.
 *
 * @package InterviewTask\Bootstrap\Http
 */
final readonly class AccountApi
{
    /**
     * Inject account and session-token owners without duplicating their rules.
     *
     * @param AccountLifecycle $lifecycle Server-side accounts and audit lifecycle.
     * @param Csrf             $csrf      Rotating session-scoped token authority.
     */
    public function __construct(
        private AccountLifecycle $lifecycle,
        private Csrf             $csrf,
    ) {}

    /**
     * Create a regular account, keeping the browser anonymous after registration.
     *
     * @param Request $request Untrusted JSON credentials.
     *
     * @return Response 201 or a documented 409/422 JSON failure.
     * @throws JsonException When a response cannot be serialized.
     * @throws Throwable When account persistence, audit or transaction handling fails.
     */
    public function register(Request $request) : Response
    {
        try {
            $fields = $this->credentials(request: $request);
            $this->lifecycle->register(username: $fields['username'], password: $fields['password']);

            return Response::json(payload: ['registered' => true], status: 201);
        } catch (UsernameTaken) {
            return self::error(code: 'username_taken', message: 'Username is already taken.', status: 409);
        } catch (RegistrationRejected $error) {
            return self::error(code: 'registration_rejected', message: $error->getMessage(), status: 422);
        } catch (InvalidArgumentException) {
            return self::error(code: 'invalid_request', message: 'Invalid registration request.', status: 422);
        }
    }

    /**
     * Establish a server session and expose a replacement CSRF token after rotation.
     *
     * @param Request $request Untrusted JSON credentials.
     *
     * @return Response 200 with public identity or 401/422 JSON failure.
     * @throws JsonException When a response cannot be serialized.
     * @throws RandomException When session CSRF rotation fails.
     * @throws Throwable When account, session, audit or diagnostics fail.
     */
    public function login(Request $request) : Response
    {
        try {
            $fields  = $this->credentials(request: $request);
            $account = $this->lifecycle->login(username: $fields['username'], password: $fields['password']);
        } catch (InvalidCredentials) {
            return self::error(code: 'invalid_login', message: 'Invalid username or password.', status: 401)
                ->withHeader(name: 'X-Request-ID', value: SecurityEvents::record(event: 'auth.login_denied'));
        } catch (InvalidArgumentException) {
            return self::error(code: 'invalid_request', message: 'Invalid login request.', status: 422);
        }

        return Response::json(payload: [
                                           'user'      => ['id' => $account->id, 'username' => $account->username, 'role' => $account->roleCode()],
                                           'csrfToken' => $this->csrf->token(),
                                       ]);
    }

    /**
     * Revoke server authentication, including its activity event, after route CSRF.
     *
     * @return Response 204 empty success.
     * @throws Throwable When account lookup, audit or session revocation fails.
     */
    public function logout() : Response
    {
        $this->lifecycle->logout();

        return new Response(status: 204);
    }

    /**
     * Validate only JSON transport shape; owner still enforces account policy.
     *
     * @param Request $request Raw API JSON request.
     *
     * @return array{username:string, password:string} Bounded submitted strings.
     * @throws InvalidArgumentException When input type or size is invalid.
     */
    private function credentials(Request $request) : array
    {
        $fields   = $request->jsonObject();
        $username = $fields['username'] ?? null;
        $password = $fields['password'] ?? null;

        if (! is_string(value: $username) || ! is_string(value: $password)
            || strlen(string: $username) > 4096 || strlen(string: $password) > 4096) {
            throw new InvalidArgumentException(message: 'Invalid credentials.');
        }

        return ['username' => $username, 'password' => $password];
    }

    /**
     * Emit bounded, stable, non-sensitive HTTP error information.
     *
     * @param string $code    Stable client error identifier.
     * @param string $message Safe human-readable explanation.
     * @param int    $status  HTTP error status.
     *
     * @return Response JSON error.
     */
    private static function error(string $code, string $message, int $status) : Response
    {
        return Response::json(payload: ['error' => ['code' => $code, 'message' => $message]], status: $status);
    }
}
