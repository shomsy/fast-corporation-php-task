<?php

declare(strict_types=1);

namespace InterviewTask\ActivityTracking;

enum ActivityAction: string
{
    case Registration = 'registration';
    case Login        = 'login';
    case Logout       = 'logout';
    case ViewPage     = 'view-page';
    case ButtonClick  = 'button-click';
}
