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

/**
 * Authorize Page B and stream the fixed artifact as an HTTP download.
 *
 * @package InterviewTask\Bootstrap\Http
 */
final readonly class DownloadPages
{
    /**
     * Bind Page B to authorized access, CSRF protection and artifact delivery.
     *
     * @param DownloadFile $downloads Fixed artifact interaction owner.
     * @param RequireSignedIn $signedIn Authorization gate for authenticated accounts.
     * @param Csrf $csrf CSRF token generation and verification component.
     * @param HtmlPage $html Trusted HTML response adapter.
     */
    public function __construct(
        private DownloadFile    $downloads,
        private RequireSignedIn $signedIn,
        private Csrf            $csrf,
        private HtmlPage        $html,
    ) {}

    /**
     * Authorize Page B, record the view and render its download form.
     *
     * @param ?Request $request Optional request for speculative-load detection.
     *
     * @return Response Result described by the native PHP return type.
     */
    public function pageB(?Request $request = null): Response
    {
        $account = $this->authorizedUser();
        if ($account instanceof Response) {
            return $account;
        }

        if ($request?->isPrefetch() === true) {
            return Response::text(body: '', status: 204);
        }

        $this->downloads->view(actorId: $account->id, actorLabel: $account->username);

        return $this->html->render(path: 'src/Downloads/page-b.php', data: [
            'csrfToken' => $this->csrf->token(),
        ]);
    }

    /**
     * Resolve a signed-in account or return the login redirect.
     *
     * @return SignedInAccount|Response Verified user identity or redirect to the login page.
     */
    private function authorizedUser(): SignedInAccount|Response
    {
        try {
            return $this->signedIn->account();
        } catch (AccessDenied) {
            return Response::redirect(location: '/login');
        }
    }

    /**
     * Validate access and CSRF before sending a fixed binary artifact; map only artifact failure to 503.
     *
     * @param Request $request Raw HTTP request; validate client-supplied values before use.
     * @return Response Result described by the native PHP return type.
     */
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
