<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Infrastructure;

use InterviewTask\Infrastructure\Database\SqliteConnection;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Prevent accidental database provisioning from ordinary application requests.
 *
 * @package InterviewTask\Tests\Infrastructure
 */
final class SqliteConnectionTest extends TestCase
{
    /**
     * An ordinary connection must not create a missing SQLite database or parent directory.
     *
     * @return void No return value.
     */
    public function testNormalConnectionDoesNotProvisionMissingDatabase() : void
    {
        $root = sys_get_temp_dir() . '/fast-no-create-' . bin2hex(string: random_bytes(length: 8));
        $path = $root . '/database/missing.sqlite';

        try {
            $this->expectException(RuntimeException::class);
            SqliteConnection::open(path: $path);
        } finally {
            self::assertDirectoryDoesNotExist($root);
        }
    }

    /**
     * Explicit CLI provisioning may create a fresh file and reopen it normally.
     *
     * @return void No return value.
     */
    public function testExplicitProvisioningCanCreateAndReopenDatabase() : void
    {
        $root = sys_get_temp_dir() . '/fast-provision-' . bin2hex(string: random_bytes(length: 8));
        $path = $root . '/database/app.sqlite';

        try {
            $pdo     = SqliteConnection::open(path: $path, createIfMissing: true);
            $journal = $pdo->query(query: 'PRAGMA journal_mode');
            self::assertNotFalse($journal);
            self::assertSame('wal', $journal->fetchColumn());

            $statement = $pdo->query(query: "SELECT 'ok'");
            self::assertNotFalse($statement);
            self::assertSame('ok', $statement->fetchColumn());
            self::assertFileExists($path);
            unset($pdo);

            $reopened  = SqliteConnection::open(path: $path);
            $statement = $reopened->query(query: "SELECT 'ok'");
            self::assertNotFalse($statement);
            self::assertSame('ok', $statement->fetchColumn());
        } finally {
            unset($journal, $statement, $reopened, $pdo);

            foreach ([$path . '-wal', $path . '-shm', $path] as $file) {
                if (is_file(filename: $file)) {
                    unlink(filename: $file);
                }
            }
            if (is_dir(filename: dirname(path: $path))) {
                rmdir(directory: dirname(path: $path));
            }
            if (is_dir(filename: $root)) {
                rmdir(directory: $root);
            }
        }
    }
}
