<?php

declare(strict_types=1);

namespace InterviewTask\Infrastructure\Time;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use Psr\Clock\ClockInterface;

/**
 * Provide a PSR-20 UTC wall clock for timestamps.
 *
 * @package InterviewTask\Infrastructure\Time
 */
final class SystemClock implements ClockInterface
{
    /**
     * Read the current UTC instant from the system clock.
     *
     * @return DateTimeImmutable The current UTC timestamp.
     */
    #[Override]
    public function now() : DateTimeImmutable
    {
        return new DateTimeImmutable(datetime: 'now', timezone: new DateTimeZone(timezone: 'UTC'));
    }
}
