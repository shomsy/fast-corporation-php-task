<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Infrastructure;

use InterviewTask\Infrastructure\Database\SqliteConnection;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Guard private SQLite storage and explicit production configuration.
 *
 * @package InterviewTask\Tests\Infrastructure
 */
final class FilePrivilegesTest extends TestCase
{
    /**
     * New SQLite files must not inherit a permissive web process umask.
     *
     * @return void No return value.
     */
    public function testNewDatabaseIsPrivateWithPermissiveCallerUmask(): void
    {
        $directory = sys_get_temp_dir() . '/fast-private-db-' . bin2hex(string: random_bytes(length: 8));
        $path = $directory . '/activity.sqlite';
        $oldUmask = umask(mask: 0000);

        try {
            $pdo = SqliteConnection::open(path: $path, createIfMissing: true);
            $statement = $pdo->query(query: 'SELECT 1');
            self::assertNotFalse($statement);
            self::assertSame(1, (int) $statement->fetchColumn());
            clearstatcache(clear_realpath_cache: true, filename: $path);
            self::assertSame(0, fileperms(filename: $path) & 0077, 'New database cannot expose group/other rights.');
            self::assertSame(0, fileperms(filename: $directory) & 0077, 'Database directory must be private.');
        } finally {
            unset($pdo);
            umask(mask: $oldUmask);
            foreach ([$path . '-wal', $path . '-shm', $path] as $file) {
                if (is_file(filename: $file)) {
                    unlink(filename: $file);
                }
            }
            if (is_dir(filename: $directory)) {
                rmdir(directory: $directory);
            }
        }
    }

    /**
     * World-readable DB permissions cannot pass production startup policy.
     *
     * @return void No return value.
     */
    public function testProductionRejectsInsecureExistingDatabase(): void
    {
        $directory = sys_get_temp_dir() . '/fast-insecure-db-' . bin2hex(string: random_bytes(length: 8));
        self::assertTrue(mkdir(directory: $directory, permissions: 0700));
        $database = $directory . '/app.sqlite';
        self::assertNotFalse(file_put_contents(filename: $database, data: 'test'));
        self::assertTrue(chmod(filename: $database, permissions: 0644));
        clearstatcache(clear_realpath_cache: true, filename: $database);
        /** @var array<string, string|false> $old */
        $old = [];
        $env = [
            'APP_ENV' => 'production', 'APP_DEBUG' => '0',
            'APP_URL' => 'https://example.test', 'SESSION_COOKIE_SECURE' => '1',
            'SESSION_STORAGE_PATH' => $directory, 'DB_PATH' => $database,
        ];
        foreach ($env as $name => $value) {
            $previous = getenv(name: $name);
            $old[$name] = is_string(value: $previous) ? $previous : false;
            putenv(assignment: $name . '=' . $value);
        }

        try {
            $rejected = false;
            try {
                require dirname(path: __DIR__, levels: 2) . '/bootstrap/config.php';
            } catch (RuntimeException) {
                $rejected = true;
            }
            self::assertTrue($rejected, 'Production must reject world-readable database files.');
        } finally {
            foreach ($old as $name => $value) {
                putenv(assignment: $value === false ? $name : $name . '=' . $value);
            }
            unlink(filename: $database);
            rmdir(directory: $directory);
        }
    }
}
