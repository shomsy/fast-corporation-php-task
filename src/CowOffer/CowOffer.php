<?php

declare(strict_types=1);

namespace InterviewTask\CowOffer;

use InterviewTask\ActivityTracking\ActivityLog;
use InterviewTask\ActivityTracking\TrackedButton;
use InterviewTask\ActivityTracking\TrackedPage;

/**
 * Page A is a transient interaction, not a persisted purchase.
 *
 * @api
 * @package InterviewTask\CowOffer
 */
final readonly class CowOffer
{
    /**
     * Bind Page A behavior to canonical activity tracking.
     *
     * @param ActivityLog $activityLog Activity-event writer shared with the feature.
     */
    public function __construct(private ActivityLog $activityLog) {}

    /**
     * Record the authorized display of Page A.
     *
     * @param int $actorId Stable numeric identity associated with the audit event.
     * @param string $actorLabel Historical username snapshot written alongside the event.
     * @return void No return value.
     */
    public function view(int $actorId, string $actorLabel): void
    {
        $this->activityLog->recordPageView(actorId: $actorId, actorLabel: $actorLabel, page: TrackedPage::PageA);
    }

    /**
     * Record an accepted button click and return the one-page thank-you outcome. No payment, order, or purchase state is persisted by this interview task.
     *
     * @param int $actorId Stable numeric identity associated with the audit event.
     * @param string $actorLabel Historical username snapshot written alongside the event.
     * @return string The literal thankYou outcome after a recorded click.
     */
    public function buy(int $actorId, string $actorLabel): string
    {
        $this->activityLog->recordButtonClick(actorId: $actorId, actorLabel: $actorLabel, button: TrackedButton::BuyCow);

        return 'thankYou';
    }
}
