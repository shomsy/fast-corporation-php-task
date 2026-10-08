<?php

declare(strict_types=1);

use InterviewTask\ActivityTracking\ActivityAction;
use InterviewTask\ActivityTracking\ActivityLog;
use InterviewTask\ActivityTracking\DailyReports;
use InterviewTask\ActivityTracking\Statistics;
use InterviewTask\ActivityTracking\TrackedButton;
use InterviewTask\ActivityTracking\TrackedPage;

return [
    'public' => [
        ActivityLog::class,
        Statistics::class,
        DailyReports::class,
        ActivityAction::class,
        TrackedPage::class,
        TrackedButton::class,
    ],
];
