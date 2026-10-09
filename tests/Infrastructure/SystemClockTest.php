<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Infrastructure;

use DateTimeImmutable;
use InterviewTask\Infrastructure\Time\SystemClock;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

/**
 * Regression checks for System clock test, covering observable behavior and failure paths.
 *
 * @package InterviewTask\Tests\Infrastructure
 */
final class SystemClockTest extends TestCase
{
    /**
     * Verify uses utc through the public behavior.
     *
     * @return void No return value.
     */
    public function testUsesUtc() : void
    {
        self::assertSame('UTC', (new SystemClock())->now()->getTimezone()->getName());
    }

    /**
     * Verify implements standard psr20 contract through the public behavior.
     *
     * @return void No return value.
     */
    public function testImplementsStandardPsr20Contract() : void
    {
        $clock = new SystemClock();

        self::assertInstanceOf(ClockInterface::class, $clock);
        self::assertInstanceOf(DateTimeImmutable::class, $clock->now());
    }
}
