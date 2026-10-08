<?php

declare(strict_types=1);

namespace InterviewTask\Bootstrap\Http;

use InterviewTask\Accounts\AccessDenied;
use InterviewTask\Accounts\CurrentAccount;
use InterviewTask\Accounts\RequireAdmin;
use InterviewTask\ActivityTracking\DailyReports;
use InterviewTask\ActivityTracking\Statistics;
use InterviewTask\Runtime\Http\Request;
use InterviewTask\Runtime\Http\Response;
use InvalidArgumentException;

final readonly class ActivityPages
{
    public function __construct(
        private Statistics     $statistics,
        private DailyReports   $reports,
        private RequireAdmin   $admin,
        private CurrentAccount $identity,
        private HtmlPage       $html,
    ) {}

    public function statisticsPage(Request $request): Response
    {
        $denied = $this->requireAdministrator();
        if ($denied !== null) {
            return $denied;
        }

        $date      = $this->optionalInput(value: $request->query(key: 'date'));
        $user      = $this->optionalInput(value: $request->query(key: 'user'));
        $action    = $this->optionalInput(value: $request->query(key: 'action'));
        $pageValue = $this->optionalInput(value: $request->query(key: 'page')) ?? '1';
        $page      = 1;

        try {
            if (! preg_match(pattern: '/^[1-9][0-9]{0,17}$/', subject: $pageValue)) {
                throw new InvalidArgumentException(message: 'Invalid page.');
            }
            $page    = (int) $pageValue;
            $result  = $this->statistics->page(date: $date, user: $user, action: $action, page: $page);
            $events  = $result['events'];
            $hasNext = $result['hasNext'];
            $error   = '';
            $status  = 200;
        } catch (InvalidArgumentException $e) {
            $events  = [];
            $hasNext = false;
            $error   = $e->getMessage();
            $status  = 422;
        }

        return $this->html->render(path: 'src/ActivityTracking/statistics.php', data: [
            'events'  => $events, 'date' => $date ?? '',
            'user'    => $user ?? '', 'action' => $action ?? '',
            'error'   => $error, 'page' => $page,
            'hasNext' => $hasNext,
        ], status: $status);
    }

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

    private function optionalInput(mixed $value): ?string
    {
        return is_string(value: $value) && $value !== '' ? $value : null;
    }

    public function reportsPage(?Request $request = null): Response
    {
        $denied = $this->requireAdministrator();
        if ($denied !== null) {
            return $denied;
        }

        $from = $request === null ? null : $this->optionalInput(value: $request->query(key: 'from'));
        $to   = $request === null ? null : $this->optionalInput(value: $request->query(key: 'to'));
        try {
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
