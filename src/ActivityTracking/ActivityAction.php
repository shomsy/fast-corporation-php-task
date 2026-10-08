<?php

declare(strict_types=1);

namespace InterviewTask\ActivityTracking;

/**
 * Canonical event action taxonomy shared by logging, filtering, and reporting.
 *
 * @api
 * @package InterviewTask\ActivityTracking
 */
enum ActivityAction: string
{
    /**
     * Canonical Registration value for the ActivityAction taxonomy.
         */
    case Registration = 'registration';
    /**
     * Canonical Login value for the ActivityAction taxonomy.
         */
    case Login        = 'login';
    /**
     * Canonical Logout value for the ActivityAction taxonomy.
         */
    case Logout       = 'logout';
    /**
     * Canonical View page value for the ActivityAction taxonomy.
         */
    case ViewPage     = 'view-page';
    /**
     * Canonical Button click value for the ActivityAction taxonomy.
         */
    case ButtonClick  = 'button-click';
}
