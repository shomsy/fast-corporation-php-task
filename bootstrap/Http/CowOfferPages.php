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

final readonly class CowOfferPages
{
    public function __construct(
        private CowOffer        $cow,
        private RequireSignedIn $signedIn,
        private Csrf            $csrf,
        private HtmlPage        $html,
    ) {}

    public function pageA(): Response
    {
        $account = $this->authorizedUser();
        if ($account instanceof Response) {
            return $account;
        }

        $this->cow->view(actorId: $account->id, actorLabel: $account->username);

        return $this->html->render(path: 'src/CowOffer/page-a.php', data: [
            'csrfToken' => $this->csrf->token(), 'bought' => false,
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
