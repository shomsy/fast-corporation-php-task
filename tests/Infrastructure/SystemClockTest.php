<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Infrastructure;

use DateTimeImmutable;
use InterviewTask\Infrastructure\Time\SystemClock;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

final class SystemClockTest extends TestCase
{
    public function testUsesUtc() : void
    {
        self::assertSame('UTC', (new SystemClock())->now()->getTimezone()->getName());
    }

    public function testImplementsStandardPsr20Contract() : void
    {
        $clock = new SystemClock();

        self::assertInstanceOf(ClockInterface::class, $clock);
        self::assertInstanceOf(DateTimeImmutable::class, $clock->now());
    }
}
