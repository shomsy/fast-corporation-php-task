<?php

declare(strict_types=1);

namespace InterviewTask\Bootstrap\Http;

use InterviewTask\Accounts\Access\AccessDenied;
use InterviewTask\Accounts\Access\RequireSignedIn;
use InterviewTask\Downloads\ArtifactUnavailable;
use InterviewTask\Downloads\DownloadFile;
use InterviewTask\Runtime\Http\Response;
use PDOException;
use Throwable;

/**
 * Page B transport: accepted view events and bounded native binary downloads.
 * The central router enforces signed-in access and CSRF before every POST.
 *
 * @package InterviewTask\Bootstrap\Http
 */
final readonly class DownloadApi
{
    /**
     * @param DownloadFile    $downloads Fixed server-selected artifact and event owner.
     * @param RequireSignedIn $signedIn  Server-authoritative identity guard.
     */
    public function __construct(
        private DownloadFile    $downloads,
        private RequireSignedIn $signedIn,
    ) {}

    /**
     * @return Response Empty 204 after an accepted Page B view event.
     * @throws AccessDenied When no authenticated account exists.
     * @throws PDOException When accepted activity persistence fails.
     * @throws Throwable When authoritative identity or another operational dependency fails.
     */
    public function view() : Response
    {
        $account = $this->signedIn->account();
        $this->downloads->view(actorId: $account->id, actorLabel: $account->username);

        return new Response(status: 204);
    }

    /**
     * Preserve browser-native POST downloads without buffering binary data in JavaScript.
     *
     * @return Response A fixed attachment after accepted audit write, or a safe 503.
     * @throws AccessDenied When no authenticated account exists.
     * @throws PDOException When accepted activity persistence fails.
     * @throws Throwable When authoritative identity or another operational dependency fails.
     */
    public function download() : Response
    {
        $account = $this->signedIn->account();

        try {
            $body = $this->downloads->prepareDownload(actorId: $account->id, actorLabel: $account->username);

            return new Response(body: $body, headers: [
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
