<?php

declare(strict_types=1);

namespace InterviewTask\Bootstrap\Http;

use InterviewTask\Accounts\AccessDenied;
use InterviewTask\Accounts\RequireSignedIn;
use InterviewTask\Accounts\SignedInAccount;
use InterviewTask\Downloads\DownloadFile;
use InterviewTask\Infrastructure\Session\Csrf;
use InterviewTask\Runtime\Http\Request;
use InterviewTask\Runtime\Http\Response;
use RuntimeException;

final readonly class DownloadPages
{
    public function __construct(
        private DownloadFile $downloads,
        private RequireSignedIn $signedIn,
        private Csrf $csrf,
        private HtmlPage $html,
    ) {}

    public function pageB(): Response
    {
        $account = $this->authorizedUser();
        if ($account instanceof Response) {
            return $account;
        }

        $this->downloads->view($account->id, $account->username);

        return $this->html->render('src/Downloads/page-b.php', [
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

    private function authorizedUser(): SignedInAccount|Response
    {
        try {
            return $this->signedIn->account();
        } catch (AccessDenied) {
            return Response::redirect('/login');
        }
    }
}
