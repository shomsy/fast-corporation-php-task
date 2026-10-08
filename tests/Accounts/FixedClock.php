<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Accounts;

use DateTimeImmutable;
use Override;
use Psr\Clock\ClockInterface;

/**
 * Supply a deterministic clock to behavior tests.
 *
 * @package InterviewTask\Tests\Accounts
 */
final readonly class FixedClock implements ClockInterface
{
    /**
     * Initialize dependencies required by this component.
     *
     * @param DateTimeImmutable $now Input value for now.
     */
    public function __construct(private DateTimeImmutable $now) {}

    /**
     * Now according to the FixedClock contract.
     *
     * @return DateTimeImmutable Result described by the native PHP return type.
     */
    #[Override]

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
