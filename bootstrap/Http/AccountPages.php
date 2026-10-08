<?php

declare(strict_types=1);

namespace InterviewTask\Bootstrap\Http;

use InterviewTask\Accounts\AccountLifecycle;
use InterviewTask\Accounts\CurrentAccount;
use InterviewTask\Accounts\Login\InvalidCredentials;
use InterviewTask\Accounts\Register\RegistrationRejected;
use InterviewTask\Accounts\UsernameTaken;
use InterviewTask\Infrastructure\Session\Csrf;
use InterviewTask\Runtime\Http\Request;
use InterviewTask\Runtime\Http\Response;
use InvalidArgumentException;

/**
 * HTTP adaptation for the Accounts owner's authentication lifecycle.
 *
 * @package InterviewTask\Bootstrap\Http
 */
final readonly class AccountPages
{
    /**
     * Receive the Accounts lifecycle, current identity, CSRF guard and HTML renderer.
     *
     * @param AccountLifecycle $lifecycle Input value for lifecycle.
     * @param CurrentAccount $identity Server-side authenticated identity access boundary.
     * @param Csrf $csrf CSRF token generation and verification component.
     * @param HtmlPage $html Trusted HTML response adapter.
     */
    public function __construct(
        private AccountLifecycle $lifecycle,
        private CurrentAccount   $identity,
        private Csrf             $csrf,
        private HtmlPage         $html,
    ) {}

    /**
     * Render the registration form with a session CSRF token.
     *
     * @return Response Result described by the native PHP return type.
     */
    public function registerForm(): Response
    {
        return $this->html->render(path: 'src/Accounts/Register/form.php', data: [
            'csrfToken' => $this->csrf->token(), 'username' => '', 'error' => '',
        ]);
    }

    /**
     * Validate CSRF and registration input, translating known business outcomes into HTTP.
     *
     * @param Request $request Raw HTTP request; validate client-supplied values before use.
     * @return Response Result described by the native PHP return type.
     */
    public function registerSubmit(Request $request): Response
    {
        if (! $this->csrf->matches(submitted: $request->post(key: 'csrf_token'))) {
            return Response::text(body: 'Forbidden', status: 403);
        }

        try {
            $username = $this->stringInput(value: $request->post(key: 'username'));
            $password = $this->stringInput(value: $request->post(key: 'password'));
        } catch (InvalidArgumentException) {
            return $this->html->render(path: 'src/Accounts/Register/form.php', data: [
                'csrfToken' => $this->csrf->token(), 'username' => '',
                'error'     => 'Invalid registration request.',
            ], status: 422);
        }

        try {
            $this->lifecycle->register(username: $username, password: $password);

            return Response::redirect(location: '/login?registered=1');
        } catch (UsernameTaken) {
            return $this->html->render(path: 'src/Accounts/Register/form.php', data: [
                'csrfToken' => $this->csrf->token(), 'username' => $username,
                'error'     => 'Username is already taken.',
            ], status: 409);
        } catch (RegistrationRejected $e) {
            return $this->html->render(path: 'src/Accounts/Register/form.php', data: [
                'csrfToken' => $this->csrf->token(), 'username' => $username,
                'error'     => $e->getMessage(),
            ], status: 422);
        }
    }

    /**
     * Convert a missing field to empty, reject non-strings and bound submitted strings to 4096 bytes.
     *
     * @param mixed $value Value to store, format, or validate, as appropriate for this operation.
     * @return string A bounded string for business-level credential validation.
     * @throws InvalidArgumentException When a credential field has an invalid type or exceeds the transport budget.
     */
    private function stringInput(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (! is_string(value: $value) || strlen(string: $value) > 4096) {
            throw new InvalidArgumentException(message: 'Invalid credential input.');
        }

        return $value;
    }

    /**
     * Render the login form and optional post-registration notice.
     *
     * @param Request $request Raw HTTP request; validate client-supplied values before use.
     * @return Response Result described by the native PHP return type.
     */
    public function loginForm(Request $request): Response
    {
        return $this->html->render(path: 'src/Accounts/Login/form.php', data: [
            'csrfToken' => $this->csrf->token(), 'registered' => $request->query(key: 'registered') === '1',
            'error'     => '',
        ]);
    }

    /**
     * Validate CSRF and sign-in credentials, translating failure to HTTP 401.
     *
     * @param Request $request Raw HTTP request; validate client-supplied values before use.
     * @return Response Result described by the native PHP return type.
     */
    public function loginSubmit(Request $request): Response
    {
        if (! $this->csrf->matches(submitted: $request->post(key: 'csrf_token'))) {
            return Response::text(body: 'Forbidden', status: 403);
        }

        try {
            $username = $this->stringInput(value: $request->post(key: 'username'));
            $password = $this->stringInput(value: $request->post(key: 'password'));
        } catch (InvalidArgumentException) {
            return $this->html->render(path: 'src/Accounts/Login/form.php', data: [
                'csrfToken'  => $this->csrf->token(),
                'registered' => false, 'error' => 'Invalid login request.',
            ], status: 422);
        }

        try {
            $this->lifecycle->login(username: $username, password: $password);
        } catch (InvalidCredentials) {
            return $this->html->render(path: 'src/Accounts/Login/form.php', data: [
                'csrfToken'  => $this->csrf->token(),
                'registered' => false, 'error' => 'Invalid username or password.',
            ], status: 401);
        }

        return Response::redirect(location: '/');
    }

    /**
     * Validate CSRF, revoke authentication and redirect to the login page.
     *
     * @param Request $request Raw HTTP request; validate client-supplied values before use.
     * @return Response Result described by the native PHP return type.
     */
    public function logoutSubmit(Request $request): Response
    {
        if (! $this->csrf->matches(submitted: $request->post(key: 'csrf_token'))) {
            return Response::text(body: 'Forbidden', status: 403);
        }

        $this->lifecycle->logout();

        return Response::redirect(location: '/login');
    }

    /**
     * Render the home page for the current verified account.
     *
     * @return Response Result described by the native PHP return type.
     */
    public function home(): Response
    {
        $account = $this->identity->current();

        if ($account === null) {
            return Response::redirect(location: '/login');
        }

        return $this->html->render(path: 'src/Accounts/home.php', data: [
            'username'  => $account->username, 'role' => $account->role->value,
            'csrfToken' => $this->csrf->token(),
        ]);
    }
}
