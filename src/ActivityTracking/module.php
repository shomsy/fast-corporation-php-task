<?php

declare(strict_types=1);

/**
 * Authoritative machine-readable exported surface of ActivityTracking.
 *
 * Audit storage and report capabilities may be called across owners only through listed contracts.
 *
 * @package InterviewTask\ActivityTracking
 */

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
