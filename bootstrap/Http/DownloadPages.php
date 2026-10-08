<?php

declare(strict_types=1);

namespace InterviewTask\Bootstrap\Http;

use InterviewTask\Accounts\AccessDenied;
use InterviewTask\Accounts\RequireSignedIn;
use InterviewTask\Accounts\SignedInAccount;
use InterviewTask\Downloads\ArtifactUnavailable;
use InterviewTask\Downloads\DownloadFile;
use InterviewTask\Infrastructure\Session\Csrf;
use InterviewTask\Runtime\Http\Request;
use InterviewTask\Runtime\Http\Response;

final readonly class DownloadPages
{
    public function __construct(
        private DownloadFile    $downloads,
        private RequireSignedIn $signedIn,
        private Csrf            $csrf,
        private HtmlPage        $html,
    ) {}

    public function pageB(): Response
    {
        $account = $this->authorizedUser();
        if ($account instanceof Response) {
            return $account;
        }

        $this->downloads->view(actorId: $account->id, actorLabel: $account->username);

        return $this->html->render(path: 'src/Downloads/page-b.php', data: [
            'csrfToken' => $this->csrf->token(),
        ]);
    }

    private function authorizedUser(): SignedInAccount|Response
    {
        try {
            return $this->signedIn->account();
        } catch (AccessDenied) {
            return Response::redirect(location: '/login');
        }
    }

    public function download(Request $request): Response
    {
        $account = $this->authorizedUser();
        if ($account instanceof Response) {
            return $account;
        }
        if (! $this->csrf->matches(submitted: $request->post(key: 'csrf_token'))) {
            return Response::text(body: 'Forbidden', status: 403);
        }

        try {
            $body = $this->downloads->read(actorId: $account->id, actorLabel: $account->username);

            return new Response(body: $body, status: 200, headers: [
                'Content-Type'           => 'application/octet-stream',
                'Content-Disposition'    => 'attachment; filename="sample.exe"',
                'Content-Length'         => (string) strlen(string: $body),
                'Cache-Control'          => 'no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (ArtifactUnavailable) {
            return Response::text(body: 'Download is temporarily unavailable.', status: 503);
        }
    }
}
