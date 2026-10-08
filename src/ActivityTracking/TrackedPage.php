<?php

declare(strict_types=1);

namespace InterviewTask\ActivityTracking;

/**
 * Canonical page targets for page-view activity records.
 *
 * @api
 * @package InterviewTask\ActivityTracking
 */
enum TrackedPage: string
{
    /**
     * Canonical Page a value for the TrackedPage taxonomy.
         */
    case PageA = 'page-a';
    /**
     * Canonical Page b value for the TrackedPage taxonomy.
         */
    case PageB = 'page-b';
}
