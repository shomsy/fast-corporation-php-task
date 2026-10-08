<?php

declare(strict_types=1);

namespace InterviewTask\ActivityTracking;

enum TrackedButton: string
{
    case BuyCow   = 'buy-cow';
    case Download = 'download';
}
