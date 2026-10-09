<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Infrastructure;

use InterviewTask\Infrastructure\Database\Migrator;
use InterviewTask\Infrastructure\Database\SqliteConnection;
use Override;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Enforce immutable migration content and all-or-nothing concurrent application.
 *
 * @package InterviewTask\Tests\Infrastructure
 */
final class MigratorIntegrityTest extends TestCase
{
    /**
     * Absolute temporary directory containing only this test instance's fixtures.
     *
     * @var string
     */
    private string $directory;

    /**
     * Create a unique migration directory for each fixture.
     *
     * @return void No return value.
     */
    #[Override]
    protected function setUp() : void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir() . '/fast-migrations-' . bin2hex(string: random_bytes(length: 8));
        self::assertTrue(mkdir(directory: $this->directory, permissions: 0700));
    }

    /**
     * Remove only files created by this test instance.
     *
     * @return void No return value.
     */
    #[Override]
    protected function tearDown() : void
    {
        foreach (glob(pattern: $this->directory . '/*') ?: [] as $file) {
            if (is_file(filename: $file)) {
                unlink(filename: $file);
            }
        }
        rmdir(directory: $this->directory);
        parent::tearDown();
    }

    /**
     * Changing an already-applied SQL file is a hard failure, not a silent skip.
     *
     * @return void No return value.
     */
    public function testRejectsChangedAppliedSql() : void
    {
        $path     = $this->sql(name: '001_init.sql', contents: 'CREATE TABLE item (id INTEGER PRIMARY KEY);');
        $pdo      = SqliteConnection::memory();
        $migrator = new Migrator(pdo: $pdo, migrationDirectories: ['Sample' => $this->directory]);
        self::assertSame(['Sample:001_init.sql'], $migrator->migrate());
        self::assertSame([], $migrator->migrate());

        file_put_contents(filename: $path, data: 'CREATE TABLE DIFFERENT (id INTEGER PRIMARY KEY);');

        $this->expectException(RuntimeException::class);
        $migrator->migrate();
    }

    /**
     * A failed later SQL must roll back previously applied migrations in the same batch.
     *
     * @return void No return value.
     */
    public function testFailedBatchDoesNotLeavePartialSchemaOrLedger() : void
    {
        $this->sql(name: '001_good.sql', contents: 'CREATE TABLE committed_too_early (id INTEGER);');
        $this->sql(name: '002_bad.sql', contents: 'THIS IS NOT VALID SQL');
        $pdo      = SqliteConnection::memory();
        $migrator = new Migrator(pdo: $pdo, migrationDirectories: ['Sample' => $this->directory]);

        try {
            $migrator->migrate();
            self::fail('Invalid migration should fail.');
        } catch (PDOException) {
            $statement = $pdo->query(query: "SELECT name FROM sqlite_master WHERE type = 'table' AND name IN ('committed_too_early', 'schema_migrations')");
            self::assertNotFalse($statement);
            self::assertSame([], $statement->fetchAll(mode: PDO::FETCH_COLUMN));
        }
    }

    /**
     * The legacy two-column ledger is upgraded without dropping historical migration IDs.
     *
     * @return void No return value.
     */
    public function testLegacyLedgerReceivesChecksumsOnFirstTrustedUpgrade() : void
    {
        $sql = 'CREATE TABLE legacy (id INTEGER);';
        $this->sql(name: '001_old.sql', contents: $sql);
        $pdo = SqliteConnection::memory();
        $pdo->exec(statement: $sql);
        $pdo->exec(statement: 'CREATE TABLE schema_migrations (migration TEXT PRIMARY KEY, applied_at TEXT NOT NULL)');
        $pdo->exec(statement: "INSERT INTO schema_migrations VALUES ('Sample:001_old.sql', '2026-10-01')");
        $migrator = new Migrator(pdo: $pdo, migrationDirectories: ['Sample' => $this->directory]);

        self::assertSame([], $migrator->migrate());
        $statement = $pdo->query(query: 'SELECT checksum FROM schema_migrations WHERE migration = \'Sample:001_old.sql\'');
        self::assertNotFalse($statement);
        self::assertSame(hash(algo: 'sha256', data: $sql), $statement->fetchColumn());
    }

    /**
     * Another migrator must not apply while the first process owns SQLite's writer reservation.
     *
     * @return void No return value.
     */
    public function testSecondRunnerWaitsForOrFailsOnMigrationLock() : void
    {
        $this->sql(name: '001_create.sql', contents: 'CREATE TABLE locked_case (id INTEGER);');
        $file   = $this->directory . '/runtime.sqlite';
        $first  = SqliteConnection::open(path: $file, createIfMissing: true);
        $second = SqliteConnection::open(path: $file);
        $second->exec(statement: 'PRAGMA busy_timeout = 50');
        $first->exec(statement: 'BEGIN IMMEDIATE');

        $runner     = new Migrator(pdo: $second, migrationDirectories: ['Sample' => $this->directory]);
        $wasBlocked = false;

        try {
            try {
                $runner->migrate();
            } catch (PDOException) {
                $wasBlocked = true;
            }
        } finally {
            $first->exec(statement: 'ROLLBACK');
        }

        self::assertTrue($wasBlocked, 'Second migration must respect the first writer lock.');
        self::assertSame(['Sample:001_create.sql'], $runner->migrate());
        self::assertSame([], $runner->migrate());
    }

    /**
     * Write a fixture SQL migration whose contents can be deliberately changed.
     *
     * @param string $name     Trusted fixture filename.
     * @param string $contents SQL fixture source text.
     *
     * @return string Absolute path to the fixture.
     */
    private function sql(string $name, string $contents) : string
    {
        $file = $this->directory . '/' . $name;
        self::assertNotFalse(file_put_contents(filename: $file, data: $contents));

        return $file;
    }
}
