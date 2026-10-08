<?php

declare(strict_types=1);

namespace InterviewTask\Tests;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ToolingSmokeTest extends TestCase
{
    public function testRequiredRuntimeCapabilitiesAreAvailable(): void
    {
        self::assertGreaterThanOrEqual(80300, PHP_VERSION_ID);
        self::assertTrue(extension_loaded(extension: 'pdo_sqlite'));
        self::assertTrue(extension_loaded(extension: 'session'));
    }
}
