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

        try {
            $events = $this->statistics->events($date, $user, $action);
            $error = '';
            $status = 200;
        } catch (InvalidArgumentException $e) {
            $events = [];
            $error = $e->getMessage();
            $status = 422;
        }

        return $this->html->render('bootstrap/views/statistics.php', [
            'events' => $events, 'date' => $date ?? '',
            'user' => $user ?? '', 'action' => $action ?? '',
            'error' => $error,
        ], $status);
    }

    public function reportsPage(): Response
    {
        $denied = $this->requireAdministrator();
        if ($denied !== null) {
            return $denied;
        }

        return $this->html->render('bootstrap/views/reports.php', [
            'daily' => $this->reports->dailyCounts(),
        ]);
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
