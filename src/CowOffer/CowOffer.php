<?php

declare(strict_types=1);

namespace InterviewTask\CowOffer;

use InterviewTask\ActivityTracking\ActivityLog;
use InterviewTask\ActivityTracking\TrackedButton;
use InterviewTask\ActivityTracking\TrackedPage;

/**
 * Page A is a transient interaction, not a persisted purchase.
 */
final readonly class CowOffer
{
    public function __construct(private ActivityLog $activityLog) {}

    public function view(int $actorId, string $actorLabel): void
    {
        $this->activityLog->recordPageView($actorId, $actorLabel, TrackedPage::PageA);
    }

    public function buy(int $actorId, string $actorLabel): string
    {
        $this->activityLog->recordButtonClick($actorId, $actorLabel, TrackedButton::BuyCow);

        return 'thankYou';
    }
}
