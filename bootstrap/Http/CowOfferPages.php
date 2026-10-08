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
        private CowOffer $cow,
        private RequireSignedIn $signedIn,
        private Csrf $csrf,
        private HtmlPage $html,
    ) {}

    public function pageA(): Response
    {
        $account = $this->authorizedUser();
        if ($account instanceof Response) {
            return $account;
        }

        $this->cow->view($account->id, $account->username);

        return $this->html->render('src/CowOffer/page-a.php', [
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

        return $this->html->render('src/CowOffer/page-a.php', [
            'csrfToken' => $this->csrf->token(), 'bought' => $result === 'thankYou',
        ]);
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
