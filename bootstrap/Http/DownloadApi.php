<?php

declare(strict_types=1);

namespace InterviewTask\Bootstrap\Http;

use InterviewTask\Accounts\RequireSignedIn;
use InterviewTask\Downloads\DownloadFile;
use InterviewTask\Runtime\Http\Response;

/**
 * JSON transport for deliberate Page B views; existing adapter owns binary delivery.
 *
 * @package InterviewTask\Bootstrap\Http
 */
final readonly class DownloadApi
{
    /**
     * Reuse the canonical artifact owner and session identity.
     *
     * @param DownloadFile    $downloads Authoritative tracked fixed-file behavior.
     * @param RequireSignedIn $signedIn  Verified account identity.
     */
    public function __construct(
        private DownloadFile    $downloads,
        private RequireSignedIn $signedIn,
    ) {}

    /**
     * Record the intentional Page B activation without preparing a binary response.
     *
     * @return Response Empty 204 after persisted view event.
     */
    public function view() : Response
    {
        $account = $this->signedIn->account();
        $this->downloads->view(actorId: $account->id, actorLabel: $account->username);

        return new Response(status: 204);
    }
}
