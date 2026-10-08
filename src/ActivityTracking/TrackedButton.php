<?php

declare(strict_types=1);

namespace InterviewTask\ActivityTracking;

/**
 * Canonical button targets for tracked button-click events.
 *
 * @api
 * @package InterviewTask\ActivityTracking
 */
enum TrackedButton: string
{
    /**
     * Canonical Buy cow value for the TrackedButton taxonomy.
         */
    case BuyCow   = 'buy-cow';
    /**
     * Canonical Download value for the TrackedButton taxonomy.
         */
    case Download = 'download';
}
