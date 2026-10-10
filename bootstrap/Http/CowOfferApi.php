<?php

declare(strict_types=1);

namespace InterviewTask\Bootstrap\Http;

use InterviewTask\Accounts\Access\AccessDenied;
use InterviewTask\Accounts\Access\RequireSignedIn;
use InterviewTask\CowOffer\CowOffer;
use InterviewTask\Runtime\Http\Response;
use JsonException;
use PDOException;
use Throwable;

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
     * @throws AccessDenied When there is no authenticated account.
     * @throws PDOException When accepted activity persistence fails.
     * @throws Throwable When authoritative identity or another operational dependency fails.
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
     * @throws AccessDenied When there is no authenticated account.
     * @throws PDOException When accepted activity persistence fails.
     * @throws JsonException When the outcome response cannot be encoded.
     * @throws Throwable When authoritative identity or another operational dependency fails.
     */
    public function buy() : Response
    {
        $account = $this->signedIn->account();
        $outcome = $this->cow->buy(actorId: $account->id, actorLabel: $account->username);

        return Response::json(payload: ['outcome' => $outcome]);
    }
}
