<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Infrastructure;

use InterviewTask\Infrastructure\Diagnostics\CleanupFailures;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

/**
 * Secondary diagnostics are observable and cannot replace the caller's primary failure.
 *
 * @package InterviewTask\Tests\Infrastructure
 */
final class CleanupFailuresTest extends TestCase
{
    /**
     * The selected sink receives the exact secondary failure.
     *
     * @return void No return value.
     */
    public function testReportsTheSecondaryFailureToTheSelectedSink() : void
    {
        $observed    = null;
        $error       = new RuntimeException(message: 'Secondary failure.');
        $diagnostics = new CleanupFailures(report: static function (Throwable $error) use (&$observed) : void {
            $observed = $error;
        });

        $diagnostics->record(error: $error);

        self::assertSame($error, $observed);
    }

    /**
     * A failed diagnostic destination stays best effort.
     *
     * @return void No return value.
     */
    public function testFailedReportingDoesNotEscapeTheCleanupBoundary() : void
    {
        $observed    = null;
        $error       = new RuntimeException(message: 'Secondary failure.');
        $diagnostics = new CleanupFailures(report: static function (Throwable $error) use (&$observed) : void {
            $observed = $error;
            throw new RuntimeException(message: 'Diagnostic destination unavailable.');
        });

        $diagnostics->record(error: $error);

        self::assertSame($error, $observed);
    }
}
