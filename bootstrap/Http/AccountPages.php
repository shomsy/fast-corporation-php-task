<?php

declare(strict_types=1);

namespace InterviewTask\Bootstrap\Http;

use InterviewTask\Accounts\AccountLifecycle;
use InterviewTask\Accounts\Login\InvalidCredentials;
use InterviewTask\Accounts\Register\RegistrationRejected;
use InterviewTask\Accounts\SessionIdentity;
use InterviewTask\Accounts\UsernameTaken;
use InterviewTask\Infrastructure\Session\Csrf;
use InterviewTask\Runtime\Http\Request;
use InterviewTask\Runtime\Http\Response;

/** HTTP adaptation for the Accounts owner's authentication lifecycle. */
final readonly class AccountPages
{
    public function __construct(
        private AccountLifecycle $lifecycle,
        private SessionIdentity $identity,
        private Csrf $csrf,
        private HtmlPage $html,
    ) {}

    public function registerForm(): Response
    {
        return $this->html->render('src/Accounts/Register/form.php', [
            'csrfToken' => $this->csrf->token(), 'username' => '', 'error' => '',
        ]);
    }

    public function registerSubmit(Request $request): Response
    {
        if (!$this->csrf->matches($request->post('csrf_token'))) {
            return Response::text('Forbidden', 403);
        }

        $username = $this->stringInput($request->post('username'));
        $password = $this->stringInput($request->post('password'));

        try {
            $this->lifecycle->register($username, $password);

            return Response::redirect('/login?registered=1');
        } catch (UsernameTaken) {
            return $this->html->render('src/Accounts/Register/form.php', [
                'csrfToken' => $this->csrf->token(), 'username' => $username,
                'error' => 'Username is already taken.',
            ], 409);
        } catch (RegistrationRejected $e) {
            return $this->html->render('src/Accounts/Register/form.php', [
                'csrfToken' => $this->csrf->token(), 'username' => $username,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function loginForm(Request $request): Response
    {
        return $this->html->render('src/Accounts/Login/form.php', [
            'csrfToken' => $this->csrf->token(), 'registered' => $request->query('registered') === '1',
            'error' => '',
        ]);
    }

    public function loginSubmit(Request $request): Response
    {
        if (!$this->csrf->matches($request->post('csrf_token'))) {
            return Response::text('Forbidden', 403);
        }

        try {
            $this->lifecycle->login(
                $this->stringInput($request->post('username')),
                $this->stringInput($request->post('password')),
            );
        } catch (InvalidCredentials) {
            return $this->html->render('src/Accounts/Login/form.php', [
                'csrfToken' => $this->csrf->token(),
                'registered' => false, 'error' => 'Invalid username or password.',
            ], 401);
        }

        return Response::redirect('/');
    }

    public function logoutSubmit(Request $request): Response
    {
        if (!$this->csrf->matches($request->post('csrf_token'))) {
            return Response::text('Forbidden', 403);
        }

        $this->lifecycle->logout();

        return Response::redirect('/login');
    }

    public function home(): Response
    {
        $account = $this->identity->current();

        if ($account === null) {
            return Response::redirect('/login');
        }

        return $this->html->render('src/Accounts/home.php', [
            'username' => $account->username, 'role' => $account->role->value,
            'csrfToken' => $this->csrf->token(),
        ]);
    }

    private function stringInput(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
