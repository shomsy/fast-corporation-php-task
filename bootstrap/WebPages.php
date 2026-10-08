<?php

declare(strict_types=1);

namespace InterviewTask\Bootstrap;

use InterviewTask\Accounts\AccessDenied;
use InterviewTask\Accounts\Accounts;
use InterviewTask\Accounts\Login\InvalidCredentials;
use InterviewTask\Accounts\Login\Login;
use InterviewTask\Accounts\Logout\Logout;
use InterviewTask\Accounts\Register\Register;
use InterviewTask\Accounts\Register\RegistrationRejected;
use InterviewTask\Accounts\RequireAdmin;
use InterviewTask\Accounts\RequireSignedIn;
use InterviewTask\Accounts\SessionIdentity;
use InterviewTask\Accounts\SignedInAccount;
use InterviewTask\Accounts\UsernameTaken;
use InterviewTask\ActivityTracking\ActivityLog;
use InterviewTask\ActivityTracking\DailyReports;
use InterviewTask\ActivityTracking\Statistics;
use InterviewTask\CowOffer\CowOffer;
use InterviewTask\Downloads\DownloadFile;
use InterviewTask\Infrastructure\Database\Transaction;
use InterviewTask\Infrastructure\Session\Csrf;
use InterviewTask\Infrastructure\Templating\Renderer;
use InterviewTask\Infrastructure\Time\Clock;
use InterviewTask\Runtime\Http\Request;
use InterviewTask\Runtime\Http\Response;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Application's HTTP edge: transport mapping only. All credentials, ownership,
 * access rules, event vocabulary and metrics belong to their business owners.
 */
final readonly class WebPages
{
    private Register $register;
    private Login $login;
    private Logout $logout;
    private RequireSignedIn $signedIn;
    private RequireAdmin $admin;
    private ActivityLog $activity;
    private CowOffer $cow;
    private DownloadFile $downloads;
    private Statistics $statistics;
    private DailyReports $reports;

    public function __construct(
        private PDO $pdo,
        Accounts $accounts,
        private SessionIdentity $identity,
        private Csrf $csrf,
        private Renderer $renderer,
        Clock $clock,
        string $artifactPath,
    ) {
        $this->register = new Register($accounts, $clock);
        $this->login = new Login($accounts, $identity);
        $this->logout = new Logout($identity);
        $this->signedIn = new RequireSignedIn($identity);
        $this->admin = new RequireAdmin($identity);
        $this->activity = new ActivityLog($pdo, $clock);
        $this->cow = new CowOffer($this->activity);
        $this->downloads = new DownloadFile($this->activity, $artifactPath);
        $this->statistics = new Statistics($pdo);
        $this->reports = new DailyReports($pdo);
    }

    public function registerForm(): Response
    {
        return $this->render('src/Accounts/Register/form.php', [
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
            (new Transaction($this->pdo))->run(function () use ($username, $password): void {
                $account = $this->register->register($username, $password);
                $this->activity->recordRegistration($account->id, $account->username);
            });

            return Response::redirect('/login?registered=1');
        } catch (UsernameTaken) {
            return $this->render('src/Accounts/Register/form.php', [
                'csrfToken' => $this->csrf->token(), 'username' => $username,
                'error' => 'Username is already taken.',
            ], 409);
        } catch (RegistrationRejected $e) {
            return $this->render('src/Accounts/Register/form.php', [
                'csrfToken' => $this->csrf->token(), 'username' => $username,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function loginForm(Request $request): Response
    {
        return $this->render('src/Accounts/Login/form.php', [
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
            $account = $this->login->login(
                $this->stringInput($request->post('username')),
                $this->stringInput($request->post('password')),
            );
        } catch (InvalidCredentials) {
            return $this->render('src/Accounts/Login/form.php', [
                'csrfToken' => $this->csrf->token(),
                'registered' => false, 'error' => 'Invalid username or password.',
            ], 401);
        }

        try {
            $this->activity->recordLogin($account->id, $account->username);
        } catch (Throwable $error) {
            $this->identity->signOut();
            throw $error;
        }

        return Response::redirect('/');
    }

    public function logoutSubmit(Request $request): Response
    {
        if (!$this->csrf->matches($request->post('csrf_token'))) {
            return Response::text('Forbidden', 403);
        }

        $account = $this->identity->current();

        try {
            if ($account !== null) {
                $this->activity->recordLogout($account->id, $account->username);
            }
        } finally {
            $this->logout->logout();
        }

        return Response::redirect('/login');
    }

    public function home(): Response
    {
        $account = $this->identity->current();

        if ($account === null) {
            return Response::redirect('/login');
        }

        return $this->render('src/Accounts/home.php', [
            'username' => $account->username, 'role' => $account->role->value,
            'csrfToken' => $this->csrf->token(),
        ]);
    }

    public function pageA(): Response
    {
        $account = $this->authorizedUser();
        if ($account instanceof Response) {
            return $account;
        }

        $this->cow->view($account->id, $account->username);

        return $this->render('bootstrap/views/page-a.php', [
            'csrfToken' => $this->csrf->token(), 'bought' => false,
        ]);
    }

    public function buyCow(Request $request): Response
    {
        $account = $this->authorizedUser();
        if ($account instanceof Response) {
            return $account;
        }
        if (!$this->csrf->matches($request->post('csrf_token'))) {
            return Response::text('Forbidden', 403);
        }

        $result = $this->cow->buy($account->id, $account->username);

        return $this->render('bootstrap/views/page-a.php', [
            'csrfToken' => $this->csrf->token(), 'bought' => $result === 'thankYou',
        ]);
    }

    public function pageB(): Response
    {
        $account = $this->authorizedUser();
        if ($account instanceof Response) {
            return $account;
        }

        $this->downloads->view($account->id, $account->username);

        return $this->render('bootstrap/views/page-b.php', [
            'csrfToken' => $this->csrf->token(),
        ]);
    }

    public function download(Request $request): Response
    {
        $account = $this->authorizedUser();
        if ($account instanceof Response) {
            return $account;
        }
        if (!$this->csrf->matches($request->post('csrf_token'))) {
            return Response::text('Forbidden', 403);
        }

        try {
            $body = $this->downloads->read($account->id, $account->username);

            return new Response($body, 200, [
                'Content-Type' => 'application/octet-stream',
                'Content-Disposition' => 'attachment; filename="sample.exe"',
                'Content-Length' => (string) strlen($body),
                'Cache-Control' => 'no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (RuntimeException) {
            return Response::text('Download is temporarily unavailable.', 503);
        }
    }

    public function statisticsPage(Request $request): Response
    {
        $denied = $this->requireAdministrator();
        if ($denied !== null) {
            return $denied;
        }

        $date = $this->optionalInput($request->query('date'));
        $user = $this->optionalInput($request->query('user'));
        $action = $this->optionalInput($request->query('action'));

        try {
            $events = $this->statistics->events($date, $user, $action);
            $error = '';
            $status = 200;
        } catch (InvalidArgumentException $e) {
            $events = [];
            $error = $e->getMessage();
            $status = 422;
        }

        return $this->render('bootstrap/views/statistics.php', [
            'events' => $events, 'date' => $date ?? '',
            'user' => $user ?? '', 'action' => $action ?? '',
            'error' => $error,
        ], $status);
    }

    public function reportsPage(): Response
    {
        $denied = $this->requireAdministrator();
        if ($denied !== null) {
            return $denied;
        }

        return $this->render('bootstrap/views/reports.php', [
            'daily' => $this->reports->dailyCounts(),
        ]);
    }

    private function requireAdministrator(): ?Response
    {
        try {
            $this->admin->account();

            return null;
        } catch (AccessDenied) {
            return $this->identity->current() === null
                ? Response::redirect('/login')
                : Response::text('Forbidden', 403);
        }
    }

    private function authorizedUser(): SignedInAccount|Response
    {
        try {
            return $this->signedIn->account();
        } catch (AccessDenied) {
            return Response::redirect('/login');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function render(string $path, array $data, int $status = 200): Response
    {
        return new Response(
            $this->renderer->render(dirname(__DIR__) . '/' . $path, $data),
            $status,
            [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Cache-Control' => 'no-store',
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'DENY',
                'Referrer-Policy' => 'no-referrer',
                'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'",
            ],
        );
    }

    private function stringInput(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function optionalInput(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
