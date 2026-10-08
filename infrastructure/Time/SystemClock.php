<?php

declare(strict_types=1);

namespace InterviewTask\Infrastructure\Time;

use DateTimeImmutable;
use DateTimeZone;
use Psr\Clock\ClockInterface;

/**
 * Provide a PSR-20 UTC wall clock for timestamps.
 *
 * @package InterviewTask\Infrastructure\Time
 */
final class SystemClock implements ClockInterface
{
    /**
     * Now according to the SystemClock contract.
     *
     * @return DateTimeImmutable Result described by the native PHP return type.
     */
    #[\Override]
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable(datetime: 'now', timezone: new DateTimeZone(timezone: 'UTC'));
    }
}
