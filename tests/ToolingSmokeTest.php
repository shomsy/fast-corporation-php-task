<?php

declare(strict_types=1);

namespace InterviewTask\Tests;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Regression checks for Tooling smoke test, covering observable behavior and failure paths.
 *
 * @package InterviewTask\Tests
 */
#[CoversNothing]
final class ToolingSmokeTest extends TestCase
{
    /**
     * Verify required runtime capabilities are available through the public behavior.
     *
     * @return void No return value.
     */
    public function testRequiredRuntimeCapabilitiesAreAvailable(): void
    {
        self::assertGreaterThanOrEqual(80300, PHP_VERSION_ID);
        self::assertTrue(extension_loaded(extension: 'pdo_sqlite'));
        self::assertTrue(extension_loaded(extension: 'session'));
    }
}
