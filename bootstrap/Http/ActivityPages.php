<?php

declare(strict_types=1);

namespace InterviewTask\Bootstrap\Http;

use InterviewTask\Accounts\AccessDenied;
use InterviewTask\Accounts\CurrentAccount;
use InterviewTask\Accounts\RequireAdmin;
use InterviewTask\ActivityTracking\DailyReports;
use InterviewTask\ActivityTracking\Statistics;
use InterviewTask\Infrastructure\Session\PhpSession;
use InterviewTask\Runtime\Http\Request;
use InterviewTask\Runtime\Http\Response;
use InvalidArgumentException;

/**
 * Adapt administrator-only statistics and reports to HTTP.
 *
 * @package InterviewTask\Bootstrap\Http
 */
final readonly class ActivityPages
{
    /**
     * Receive query capabilities, the admin authorization guard and HTML adapter.
     *
     * @param Statistics $statistics Activity-history read model.
     * @param DailyReports $reports Daily activity report read model.
     * @param RequireAdmin $admin Administrator authorization gate.
     * @param CurrentAccount $identity Server-side authenticated identity access boundary.
     * @param HtmlPage $html Trusted HTML response adapter.
     * @param PhpSession $session Owner of server-only cursor signing state.
     */
    public function __construct(
        private Statistics     $statistics,
        private DailyReports   $reports,
        private RequireAdmin   $admin,
        private CurrentAccount $identity,
        private HtmlPage       $html,
        private PhpSession     $session,
    ) {}

    /**
     * Authorize the caller, parse filters, and render one paginated activity page.
     *
     * @param Request $request Raw HTTP request; validate client-supplied values before use.
     * @return Response Authorized statistics response or a redirect/forbidden response.
     */
    public function statisticsPage(Request $request): Response
    {
        $denied = $this->requireAdministrator();
        if ($denied !== null) {
            return $denied;
        }

        $date   = null;
        $user   = null;
        $action = null;
        $page   = 1;

        try {
            $date      = $this->optionalInput(value: $request->query(key: 'date'));
            $user      = $this->optionalInput(value: $request->query(key: 'user'));
            $action    = $this->optionalInput(value: $request->query(key: 'action'));
            $pageInput = $this->optionalInput(value: $request->query(key: 'page'));
            $cursor = $this->optionalInput(value: $request->query(key: 'cursor'));
            $pageValue = $pageInput ?? '1';

            if (! preg_match(pattern: '/^[1-9][0-9]{0,17}$/', subject: $pageValue)) {
                throw new InvalidArgumentException(message: 'Invalid page.');
            }
            $page = (int) $pageValue;
            if ($cursor !== null && $pageInput !== null) {
                throw new InvalidArgumentException(message: 'Page and cursor cannot be combined.');
            }
            $cursorMode = $pageInput === null;
            if ($cursorMode) {
                $secret = $this->session->get(key: 'activity_cursor_secret');
                if (! is_string(value: $secret) || $secret === '') {
                    $secret = bin2hex(string: random_bytes(length: 32));
                    $this->session->put(key: 'activity_cursor_secret', value: $secret);
                }
                $result = $this->statistics->cursorPage(cursor: $cursor, key: $secret, date: $date, user: $user, action: $action);
                $nextCursor = $result['nextCursor'];
                $previousCursor = $result['previousCursor'];
            } else {
                $result = $this->statistics->page(date: $date, user: $user, action: $action, page: $page);
                $nextCursor = null;
                $previousCursor = null;
            }
            $events = $result['events'];
            $hasNext = $result['hasNext'];
            $error   = '';
            $status  = 200;
        } catch (InvalidArgumentException $e) {
            $cursorMode = true;
            $nextCursor = null;
            $previousCursor = null;
            $events  = [];
            $hasNext = false;
            $error   = $e->getMessage();
            $status  = 422;
        }

        return $this->html->render(path: 'src/ActivityTracking/statistics.php', data: [
            'events'  => $events, 'date' => $date ?? '',
            'user'    => $user ?? '', 'action' => $action ?? '',
            'error'   => $error, 'page' => $page,
            'hasNext' => $hasNext, 'cursorMode' => $cursorMode,
            'nextCursor' => $nextCursor, 'previousCursor' => $previousCursor,
        ], status: $status);
    }

    /**
     * Return a redirect or forbidden response when administrator access is absent.
     *
     * @return Response|null Denial response, or null when the user is an administrator.
     */
    private function requireAdministrator(): ?Response
    {
        try {
            $this->admin->account();

            return null;
        } catch (AccessDenied) {
            return $this->identity->current() === null
                ? Response::redirect(location: '/login')
                : Response::text(body: 'Forbidden', status: 403);
        }
    }

    /**
     * Preserve a non-empty string input, otherwise treat it as missing.
     *
     * @param mixed $value Value to store, format, or validate, as appropriate for this operation.
     * @return string|null The non-empty string provided, otherwise null.
     */
    private function optionalInput(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string(value: $value) || strlen(string: $value) > 4096) {
            throw new InvalidArgumentException(message: 'Invalid query parameter.');
        }

        return $value;
    }

    /**
     * Authorize and render the bounded daily report for optional UTC dates.
     *
     * @param Request|null $request Raw HTTP request; validate client-supplied values before use.
     * @return Response Authorized report response or a redirect/forbidden response.
     */
    public function reportsPage(?Request $request = null): Response
    {
        $denied = $this->requireAdministrator();
        if ($denied !== null) {
            return $denied;
        }

        $from = null;
        $to   = null;

        try {
            $from   = $request === null ? null : $this->optionalInput(value: $request->query(key: 'from'));
            $to     = $request === null ? null : $this->optionalInput(value: $request->query(key: 'to'));
            $daily  = $this->reports->dailyCounts(from: $from, to: $to);
            $error  = '';
            $status = 200;
        } catch (InvalidArgumentException $e) {
            $daily  = [];
            $error  = $e->getMessage();
            $status = 422;
        }

        return $this->html->render(path: 'src/ActivityTracking/reports.php', data: [
            'daily' => $daily, 'from' => $from ?? '', 'to' => $to ?? '', 'error' => $error,
        ], status: $status);
    }
}
