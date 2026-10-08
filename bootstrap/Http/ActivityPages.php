<?php

declare(strict_types=1);

namespace InterviewTask\Bootstrap\Http;

use InterviewTask\Accounts\AccessDenied;
use InterviewTask\Accounts\RequireAdmin;
use InterviewTask\Accounts\SessionIdentity;
use InterviewTask\ActivityTracking\DailyReports;
use InterviewTask\ActivityTracking\Statistics;
use InterviewTask\Runtime\Http\Request;
use InterviewTask\Runtime\Http\Response;
use InvalidArgumentException;

final readonly class ActivityPages
{
    public function __construct(
        private Statistics $statistics,
        private DailyReports $reports,
        private RequireAdmin $admin,
        private SessionIdentity $identity,
        private HtmlPage $html,
    ) {}

    public function statisticsPage(Request $request): Response
    {
        $denied = $this->requireAdministrator();
        if ($denied !== null) {
            return $denied;
        }

        $date = $this->optionalInput($request->query('date'));
        $user = $this->optionalInput($request->query('user'));
        $action = $this->optionalInput($request->query('action'));
        $pageValue = $this->optionalInput($request->query('page')) ?? '1';
        $page = 1;

        try {
            if (!preg_match('/^[1-9][0-9]{0,3}$/', $pageValue)) {
                throw new InvalidArgumentException('Invalid page.');
            }
            $page = (int) $pageValue;
            $events = $this->statistics->events($date, $user, $action, $page);
            $error = '';
            $status = 200;
        } catch (InvalidArgumentException $e) {
            $events = [];
            $error = $e->getMessage();
            $status = 422;
        }

        return $this->html->render('src/ActivityTracking/statistics.php', [
            'events' => $events, 'date' => $date ?? '',
            'user' => $user ?? '', 'action' => $action ?? '',
            'error' => $error, 'page' => $page,
            'hasNext' => count($events) === Statistics::PAGE_SIZE,
        ], $status);
    }

    public function reportsPage(?Request $request = null): Response
    {
        $denied = $this->requireAdministrator();
        if ($denied !== null) {
            return $denied;
        }

        $from = $request === null ? null : $this->optionalInput($request->query('from'));
        $to = $request === null ? null : $this->optionalInput($request->query('to'));
        try {
            $daily = $this->reports->dailyCounts($from, $to);
            $error = '';
            $status = 200;
        } catch (InvalidArgumentException $e) {
            $daily = [];
            $error = $e->getMessage();
            $status = 422;
        }

        return $this->html->render('src/ActivityTracking/reports.php', [
            'daily' => $daily, 'from' => $from ?? '', 'to' => $to ?? '', 'error' => $error,
        ], $status);
    }

    private function requireAdministrator(): ?Response
    {
        try {
            $this->admin->account();

            return null;
        } catch (AccessDenied) {
            return $this->identity->current() === null
                ? Response::redirect('/login')
                : Response::text('Forbidden', 403);
        }
    }

    private function optionalInput(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
