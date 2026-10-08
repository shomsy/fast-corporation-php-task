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

/** HTTP adaptation for the Accounts owner's authentication lifecycle. */
final readonly class AccountPages
{
    public function __construct(
        private AccountLifecycle $lifecycle,
        private CurrentAccount   $identity,
        private Csrf             $csrf,
        private HtmlPage         $html,
    ) {}

    public function registerForm(): Response
    {
        return $this->html->render(path: 'src/Accounts/Register/form.php', data: [
            'csrfToken' => $this->csrf->token(), 'username' => '', 'error' => '',
        ]);
    }

    public function registerSubmit(Request $request): Response
    {
        if (! $this->csrf->matches(submitted: $request->post(key: 'csrf_token'))) {
            return Response::text(body: 'Forbidden', status: 403);
        }

        $username = $this->stringInput(value: $request->post(key: 'username'));
        $password = $this->stringInput(value: $request->post(key: 'password'));

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

    private function stringInput(mixed $value): string
    {
        return is_string(value: $value) ? $value : '';
    }

    public function loginForm(Request $request): Response
    {
        return $this->html->render(path: 'src/Accounts/Login/form.php', data: [
            'csrfToken' => $this->csrf->token(), 'registered' => $request->query(key: 'registered') === '1',
            'error'     => '',
        ]);
    }

    public function loginSubmit(Request $request): Response
    {
        if (! $this->csrf->matches(submitted: $request->post(key: 'csrf_token'))) {
            return Response::text(body: 'Forbidden', status: 403);
        }

        try {
            $this->lifecycle->login(
                username: $this->stringInput(value: $request->post(key: 'username')),
                password: $this->stringInput(value: $request->post(key: 'password')),
            );
        } catch (InvalidCredentials) {
            return $this->html->render(path: 'src/Accounts/Login/form.php', data: [
                'csrfToken'  => $this->csrf->token(),
                'registered' => false, 'error' => 'Invalid username or password.',
            ], status: 401);
        }

        return Response::redirect(location: '/');
    }

    public function logoutSubmit(Request $request): Response
    {
        if (! $this->csrf->matches(submitted: $request->post(key: 'csrf_token'))) {
            return Response::text(body: 'Forbidden', status: 403);
        }

        $this->lifecycle->logout();

        return Response::redirect(location: '/login');
    }

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
