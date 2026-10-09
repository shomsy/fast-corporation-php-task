<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Infrastructure;

use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Validate deployment policy before an HTTP request can accept traffic.
 *
 * @package InterviewTask\Tests\Infrastructure
 */
final class DeploymentConfigTest extends TestCase
{
    /**
     * Production mode cannot use a browser session cookie that is sent over HTTP.
     *
     * @return void No return value.
     */
    public function testProductionRejectsInsecureSessionCookie(): void
    {
        $threw = false;

        $this->withEnvironment(
            overrides: ['APP_ENV' => 'production', 'APP_DEBUG' => '0', 'APP_URL' => 'https://example.test', 'SESSION_COOKIE_SECURE' => '0'],
            action: static function () use (&$threw): void {
                try {
                    require dirname(path: __DIR__, levels: 2) . '/bootstrap/config.php';
                } catch (RuntimeException) {
                    $threw = true;
                }
            },
        );

        self::assertTrue($threw, 'Insecure production cookie must fail closed.');
    }

    /**
     * Production mode must disable debugging and reject insecure public URLs.
     *
     * @return void No return value.
     */
    public function testProductionRejectsDebugAndInsecureOrigin(): void
    {
        foreach ([
            ['APP_ENV' => 'production', 'APP_DEBUG' => '1', 'APP_URL' => 'https://example.test', 'SESSION_COOKIE_SECURE' => '1'],
            ['APP_ENV' => 'production', 'APP_DEBUG' => '0', 'APP_URL' => 'http://example.test', 'SESSION_COOKIE_SECURE' => '1'],
        ] as $overrides) {
            $threw = false;

            $this->withEnvironment(overrides: $overrides, action: static function () use (&$threw): void {
                try {
                    require dirname(path: __DIR__, levels: 2) . '/bootstrap/config.php';
                } catch (RuntimeException) {
                    $threw = true;
                }
            });

            self::assertTrue($threw, 'Unsafe production config must fail closed.');
        }
    }

    /**
     * Production must fail closed without a dedicated private session directory.
     *
     * @return void No return value.
     */
    public function testProductionRejectsMissingPrivateSessionDirectory(): void
    {
        $rejected = false;
        $this->withEnvironment(
            overrides: [
                'APP_ENV' => 'production', 'APP_DEBUG' => '0',
                'APP_URL' => 'https://example.test', 'SESSION_COOKIE_SECURE' => '1',
                'SESSION_STORAGE_PATH' => '',
            ],
            action: static function () use (&$rejected): void {
                try {
                    require dirname(path: __DIR__, levels: 2) . '/bootstrap/config.php';
                } catch (RuntimeException) {
                    $rejected = true;
                }
            },
        );

        self::assertTrue($rejected, 'Production requires dedicated private session storage.');
    }

    /**
     * Environment mutations used in tests must not leak between test cases.
     *
     * @param array<string, string> $overrides Trusted test configuration.
     * @param callable(): void $action Synchronous validation call.
     * @return void No return value.
     */
    private function withEnvironment(array $overrides, callable $action): void
    {
        /** @var array<string, string|false> $previous */
        $previous = [];

        foreach ($overrides as $key => $value) {
            $old = getenv(name: $key);
            $previous[$key] = is_string(value: $old) ? $old : false;
            putenv(assignment: $key . '=' . $value);
        }

        try {
            $action();
        } finally {
            foreach ($previous as $key => $value) {
                if ($value === false) {
                    putenv(assignment: $key);
                } else {
                    putenv(assignment: $key . '=' . $value);
                }
            }
        }
    }

    /**
     * Runtime bootstrap must reject an existing SQLite file without applied migrations.
     *
     * @return void No return value.
     */
    public function testRuntimeDoesNotServeUnmigratedDatabase(): void
    {
        $path = tempnam(directory: sys_get_temp_dir(), prefix: 'unmigrated-');
        self::assertNotFalse($path);

        try {
            $rejected = false;
            $this->withEnvironment(
                overrides: ['APP_ENV' => 'test', 'DB_PATH' => $path],
                action: static function () use (&$rejected): void {
                    try {
                        require dirname(path: __DIR__, levels: 2) . '/bootstrap/app.php';
                    } catch (\PDOException|RuntimeException) {
                        $rejected = true;
                    }
                },
            );

            self::assertTrue($rejected);
        } finally {
            unlink(filename: $path);
        }
    }

    /**
     * A production browser request must not trust plain HTTP transport.
     *
     * @return void No return value.
     */
    public function testProductionRuntimeRequiresTrustedTls(): void
    {
        $sessionPath = sys_get_temp_dir() . '/fast-private-session-' . bin2hex(string: random_bytes(length: 8));
        self::assertTrue(mkdir(directory: $sessionPath, permissions: 0700));
        $previousHttps = $_SERVER['HTTPS'] ?? null;
        $_SERVER['HTTPS'] = 'off';

        try {
            $rejected = false;
            $this->withEnvironment(
                overrides: [
                    'APP_ENV'               => 'production',
                    'APP_DEBUG'             => '0',
                    'APP_URL'               => 'https://example.test',
                    'SESSION_COOKIE_SECURE' => '1',
                    'SESSION_STORAGE_PATH'  => $sessionPath,
                ],
                action: static function () use (&$rejected): void {
                    try {
                        require dirname(path: __DIR__, levels: 2) . '/bootstrap/app.php';
                    } catch (RuntimeException $error) {
                        $rejected = str_contains(haystack: $error->getMessage(), needle: 'HTTPS');
                    }
                },
            );

            self::assertTrue($rejected);
        } finally {
            if ($previousHttps === null) {
                unset($_SERVER['HTTPS']);
            } else {
                $_SERVER['HTTPS'] = $previousHttps;
            }

            rmdir(directory: $sessionPath);
        }
    }


    /**
     * Private session storage must not be group- or world-accessible.
     *
     * @return void No return value.
     */
    public function testProductionRejectsGroupReadableSessionDirectory(): void
    {
        $path = sys_get_temp_dir() . '/fast-public-session-' . bin2hex(string: random_bytes(length: 8));
        self::assertTrue(mkdir(directory: $path, permissions: 0755));

        try {
            $rejected = false;
            $this->withEnvironment(
                overrides: [
                    'APP_ENV' => 'production', 'APP_DEBUG' => '0',
                    'APP_URL' => 'https://example.test', 'SESSION_COOKIE_SECURE' => '1',
                    'SESSION_STORAGE_PATH' => $path,
                ],
                action: static function () use (&$rejected): void {
                    try {
                        require dirname(path: __DIR__, levels: 2) . '/bootstrap/config.php';
                    } catch (RuntimeException) {
                        $rejected = true;
                    }
                },
            );
            self::assertTrue($rejected);
        } finally {
            rmdir(directory: $path);
        }
    }

    /**
     * A precreated private session directory is valid for production.
     *
     * @return void No return value.
     */
    public function testProductionAcceptsPrivateSessionDirectory(): void
    {
        $path = sys_get_temp_dir() . '/fast-valid-session-' . bin2hex(string: random_bytes(length: 8));
        self::assertTrue(mkdir(directory: $path, permissions: 0700));

        try {
            $this->withEnvironment(
                overrides: [
                    'APP_ENV' => 'production', 'APP_DEBUG' => '0',
                    'APP_URL' => 'https://example.test', 'SESSION_COOKIE_SECURE' => '1',
                    'SESSION_STORAGE_PATH' => $path,
                ],
                action: static function () use ($path): void {
                    $config = require dirname(path: __DIR__, levels: 2) . '/bootstrap/config.php';
                    self::assertIsArray($config);
                    self::assertSame($path, $config['sessionStoragePath']);
                },
            );
        } finally {
            rmdir(directory: $path);
        }
    }

}
