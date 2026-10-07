<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Accounts;

use DateTimeImmutable;
use InterviewTask\Infrastructure\Time\Clock;

final readonly class FixedClock implements Clock
{
    public function __construct(private DateTimeImmutable $now) {}

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
