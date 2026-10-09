<?php

declare(strict_types=1);

namespace InterviewTask\Bootstrap\Http;

use InterviewTask\Accounts\RequireSignedIn;
use InterviewTask\CowOffer\CowOffer;
use InterviewTask\Runtime\Http\Response;

/**
 * JSON transport for the existing Page A interactions and canonical audit.
 *
 * @package InterviewTask\Bootstrap\Http
 */
final readonly class CowOfferApi
{
    /**
     * Inject the CowOffer owner and authoritative identity gate.
     *
     * @param CowOffer        $cow      Business owner of view and accepted button-click events.
     * @param RequireSignedIn $signedIn Server-side verified actor identity.
     */
    public function __construct(
        private CowOffer        $cow,
        private RequireSignedIn $signedIn,
    ) {}

    /**
     * Record a deliberate authenticated Page A route activation, never a prefetch.
     *
     * @return Response Empty 204 after accepted view audit.
     */
    public function view() : Response
    {
        $account = $this->signedIn->account();
        $this->cow->view(actorId: $account->id, actorLabel: $account->username);

        return new Response(status: 204);
    }

    /**
     * Record an accepted click and return transient thank-you UI outcome.
     *
     * @return Response JSON outcome, emitted only after successful audit write.
     */
    public function buy() : Response
    {
        $account = $this->signedIn->account();
        $outcome = $this->cow->buy(actorId: $account->id, actorLabel: $account->username);

        return Response::json(payload: ['outcome' => $outcome]);
    }
}
