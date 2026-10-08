<?php

declare(strict_types=1);

namespace InterviewTask\Infrastructure\Time;

use DateTimeImmutable;
use DateTimeZone;
use Psr\Clock\ClockInterface;

final class SystemClock implements ClockInterface
{
    #[\Override]
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable(datetime: 'now', timezone: new DateTimeZone(timezone: 'UTC'));
    }
}
