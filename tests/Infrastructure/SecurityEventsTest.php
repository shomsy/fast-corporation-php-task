<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Infrastructure;

use InterviewTask\Infrastructure\Diagnostics\SecurityEvents;
use InvalidArgumentException;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * Test that security diagnostics preserve correlation without exposing raw sensitive values.
 *
 * @package InterviewTask\Tests\Infrastructure
 */
final class SecurityEventsTest extends TestCase
{
    /**
     * SQLite contention logs only an allowed event, safe exception class and request ID.
     *
     * @return void No return value.
     */
    public function testContentionLoggingRedactsExceptionMessages() : void
    {
        $file = tempnam(directory: sys_get_temp_dir(), prefix: 'fast-security-events-');
        self::assertNotFalse($file);
        $original = ini_set(option: 'error_log', value: $file);
        self::assertNotFalse($original);

        try {
            $error            = new PDOException(message: 'password=do-not-log-me');
            $error->errorInfo = ['HY000', 5, 'private query'];
            $id               = SecurityEvents::record(event: 'runtime.unhandled', error: $error);
            self::assertMatchesRegularExpression('/\A[a-f0-9]{24}\z/', $id);
            $logged = file_get_contents(filename: $file);
            self::assertIsString($logged);
            self::assertStringContainsString('"event":"db.contention"', $logged);
            self::assertStringContainsString('"exception_class":"PDOException"', $logged);
            self::assertStringContainsString('"request_id":"' . $id . '"', $logged);
            self::assertStringNotContainsString('do-not-log-me', $logged);
            self::assertStringNotContainsString('private query', $logged);
        } finally {
            ini_set(option: 'error_log', value: $original);
            unlink(filename: $file);
        }
    }

    /**
     * Untrusted log categories cannot forge event records.
     *
     * @return void No return value.
     */
    public function testUnrecognizedEventNamesAreRejected() : void
    {
        $this->expectException(InvalidArgumentException::class);

        SecurityEvents::record(event: "auth.login_denied\nforged");
    }
}
