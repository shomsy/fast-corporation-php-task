<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Runtime;

use InterviewTask\Bootstrap\Http\HealthProbes;
use InterviewTask\Infrastructure\Database\Migrator;
use InterviewTask\Infrastructure\Database\SqliteConnection;
use InterviewTask\Runtime\Http\Request;
use PHPUnit\Framework\TestCase;

/**
 * Operational probes must not depend on authentication or accidentally repair a corrupt database.
 *
 * @package InterviewTask\Tests\Runtime
 */
final class HealthProbesTest extends TestCase
{
    /**
     * The process is live without a database, while readiness is fail-closed.
     *
     * @return void No return value.
     */
    public function testLivenessAndUnavailableReadinessDoNotCreateDatabase() : void
    {
        $path = sys_get_temp_dir() . '/fast-health-absent-' . bin2hex(string: random_bytes(length: 8)) . '.sqlite';
        $this->withDbPath(path: $path, check: static function () use ($path) : void {
            $health = HealthProbes::respond(request: new Request(method: 'GET', path: '/healthz'));
            self::assertNotNull($health);
            self::assertSame(200, $health->status());
            self::assertSame('ok', $health->body());

            $readiness = HealthProbes::respond(request: new Request(method: 'GET', path: '/readyz'));
            self::assertNotNull($readiness);
            self::assertSame(503, $readiness->status());
            self::assertSame('Not Ready', $readiness->body());
            self::assertFileDoesNotExist($path);
        });
    }

    /**
     * The migration source checksum is an actual readiness prerequisite, not merely a row count.
     *
     * @return void No return value.
     */
    public function testReadinessDetectsMigrationChecksumDrift() : void
    {
        $path = sys_get_temp_dir() . '/fast-health-hash-' . bin2hex(string: random_bytes(length: 8)) . '.sqlite';
        $pdo  = SqliteConnection::open(path: $path, createIfMissing: true);
        (new Migrator(pdo: $pdo, migrationDirectories: [
            'Accounts'         => dirname(path: __DIR__, levels: 2) . '/src/Accounts/migrations',
            'ActivityTracking' => dirname(path: __DIR__, levels: 2) . '/src/ActivityTracking/migrations',
        ]))->migrate();

        try {
            $this->withDbPath(path: $path, check: static function () use ($pdo) : void {
                $healthy = HealthProbes::respond(request: new Request(method: 'GET', path: '/readyz'));
                self::assertNotNull($healthy);
                self::assertSame(200, $healthy->status());

                $pdo->exec(statement: "UPDATE schema_migrations SET checksum = 'tampered' WHERE migration = 'Accounts:001_create_accounts.sql'");
                $rejected = HealthProbes::respond(request: new Request(method: 'GET', path: '/readyz'));
                self::assertNotNull($rejected);
                self::assertSame(503, $rejected->status());
                self::assertSame('Not Ready', $rejected->body());
            });
        } finally {
            unset($pdo);
            foreach ([$path . '-wal', $path . '-shm', $path] as $file) {
                if (is_file(filename: $file)) {
                    unlink(filename: $file);
                }
            }
        }
    }

    /**
     * Method semantics are stable and do not initialize infrastructure for OPTIONS or POST.
     *
     * @return void No return value.
     */
    public function testProbeMethodNegotiationHasNoBusinessSideEffects() : void
    {
        $option = HealthProbes::respond(request: new Request(method: 'OPTIONS', path: '/readyz'));
        self::assertNotNull($option);
        self::assertSame(204, $option->status());
        self::assertSame('GET, HEAD, OPTIONS', $option->headers()['Allow']);

        $post = HealthProbes::respond(request: new Request(method: 'POST', path: '/readyz'));
        self::assertNotNull($post);
        self::assertSame(405, $post->status());
        self::assertSame('GET, HEAD, OPTIONS', $post->headers()['Allow']);

        self::assertNull(HealthProbes::respond(request: new Request(method: 'GET', path: '/page-a')));
    }

    /**
     * Temporarily bind only the dependency path without changing saved environment or secrets.
     *
     * @param string           $path  Test-owned SQLite path.
     * @param callable(): void $check Probe assertions.
     *
     * @return void No return value.
     */
    private function withDbPath(string $path, callable $check) : void
    {
        $before = getenv(name: 'DB_PATH');
        putenv(assignment: 'DB_PATH=' . $path);

        try {
            $check();
        } finally {
            if (is_string(value: $before)) {
                putenv(assignment: 'DB_PATH=' . $before);
            } else {
                putenv(assignment: 'DB_PATH');
            }
        }
    }
}
