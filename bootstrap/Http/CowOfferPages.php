<?php

declare(strict_types=1);

namespace InterviewTask\Bootstrap\Http;

use InterviewTask\Accounts\AccessDenied;
use InterviewTask\Accounts\RequireSignedIn;
use InterviewTask\Accounts\SignedInAccount;
use InterviewTask\CowOffer\CowOffer;
use InterviewTask\Infrastructure\Session\Csrf;
use InterviewTask\Runtime\Http\Request;
use InterviewTask\Runtime\Http\Response;

/**
 * Authorize Page A access and protect the buy-cow action with CSRF validation.
 *
 * @package InterviewTask\Bootstrap\Http
 */
final readonly class CowOfferPages
{
    /**
     * Bind Page A behavior to login authorization, CSRF and HTML rendering.
     *
     * @param CowOffer $cow Page A business interaction owner.
     * @param RequireSignedIn $signedIn Authorization gate for authenticated accounts.
     * @param Csrf $csrf CSRF token generation and verification component.
     * @param HtmlPage $html Trusted HTML response adapter.
     */
    public function __construct(
        private CowOffer        $cow,
        private RequireSignedIn $signedIn,
        private Csrf            $csrf,
        private HtmlPage        $html,
    ) {}

    /**
     * Authorize Page A, record the view and return its initial form.
     *
     * @param ?Request $request Optional request for speculative-load detection.
     *
     * @return Response Page A HTML after an accepted view; 204 for prefetch, or login redirect.
     */
    public function pageA(?Request $request = null): Response
    {
        $account = $this->authorizedUser();
        if ($account instanceof Response) {
            return $account;
        }

        if ($request?->isPrefetch() === true) {
            return Response::text(body: '', status: 204);
        }

        $this->cow->view(actorId: $account->id, actorLabel: $account->username);

        return $this->html->render(path: 'src/CowOffer/page-a.php', data: [
            'csrfToken' => $this->csrf->token(), 'bought' => false,
        ]);
    }

    /**
     * Return an authorized user or an HTTP login redirect.
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
     * Validate login and CSRF, record the click, then render the thank-you view.
     *
     * @param Request $request Raw HTTP request; validate client-supplied values before use.
     * @return Response Thank-you HTML only after an accepted audit write; 403 CSRF or login redirect.
     */
    public function buyCow(Request $request): Response
    {
        $account = $this->authorizedUser();
        if ($account instanceof Response) {
            return $account;
        }
        if (! $this->csrf->matches(submitted: $request->post(key: 'csrf_token'))) {
            return Response::text(body: 'Forbidden', status: 403);
        }

        $result = $this->cow->buy(actorId: $account->id, actorLabel: $account->username);

        return $this->html->render(path: 'src/CowOffer/page-a.php', data: [
            'csrfToken' => $this->csrf->token(), 'bought' => $result === 'thankYou',
        ]);
    }
}
