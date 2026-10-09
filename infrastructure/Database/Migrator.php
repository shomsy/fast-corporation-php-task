<?php

declare(strict_types=1);

namespace InterviewTask\Infrastructure\Database;

use PDO;
use RuntimeException;

/**
 * Discover, apply and record owner SQL migrations with transactional bookkeeping.
 *
 * @package InterviewTask\Infrastructure\Database
 */
final readonly class Migrator
{
    /**
     * Register the configured database and owner-to-directory migration mapping.
     *
     * @param PDO                   $pdo                  SQLite PDO connection with configured driver settings.
     * @param array<string, string> $migrationDirectories owner => absolute directory
     */
    public function __construct(
        private readonly PDO   $pdo,
        private readonly array $migrationDirectories,
    ) {}

    /**
     * Validate applied SQL and atomically apply any missing migrations under one SQLite writer lock.
     *
     * @return list<string> Migration identifiers newly applied by this run.
     * @throws RuntimeException When migration content, source or metadata is invalid.
     */
    public function migrate() : array
    {
        $migrations = $this->discoverMigrations();
        $this->pdo->exec(statement: 'BEGIN IMMEDIATE');

        try {
            $this->ensureMetadataTable();
            $this->upgradeLegacyLedger();
            $applied = $this->appliedMigrations();
            $added   = [];

            foreach ($applied as $id => $checksum) {
                if (! isset($migrations[$id])) {
                    throw new RuntimeException(message: 'Recorded migration source missing: ' . $id);
                }

                $actual = hash(algo: 'sha256', data: $this->readSql(file: $migrations[$id]));
                if ($checksum !== null && ! hash_equals(known_string: $checksum, user_string: $actual)) {
                    throw new RuntimeException(message: 'Applied migration changed: ' . $id);
                }

                if ($checksum === null) {
                    $statement = $this->pdo->prepare(query: 'UPDATE schema_migrations SET checksum = :hash WHERE migration = :id');
                    if ($statement === false) {
                        throw new RuntimeException(message: 'Cannot update legacy checksum.');
                    }
                    $statement->execute(params: ['hash' => $actual, 'id' => $id]);
                }
            }

            foreach ($migrations as $id => $file) {
                if (array_key_exists(key: $id, array: $applied)) {
                    continue;
                }

                $sql = $this->readSql(file: $file);
                $this->pdo->exec(statement: $sql);
                $statement = $this->pdo->prepare(query: 'INSERT INTO schema_migrations (migration, checksum, applied_at) VALUES (:id, :hash, :at)');
                if ($statement === false) {
                    throw new RuntimeException(message: 'Cannot record migration.');
                }
                $statement->execute(params: ['id' => $id, 'hash' => hash(algo: 'sha256', data: $sql), 'at' => gmdate(format: 'c')]);
                $added[] = $id;
            }

            $this->pdo->exec(statement: 'COMMIT');

            return $added;
        } catch (\Throwable $error) {
            try {
                $this->pdo->exec(statement: 'ROLLBACK');
            } catch (\Throwable) {
                // Keep the first failure visible.
            }
            throw $error;
        }
    }

    /**
     * Create the migration ledger without changing owner schema.
     *
     * @return void No return value.
     */
    private function ensureMetadataTable() : void
    {
        $this->pdo->exec(
            statement: <<<'SQL'
                CREATE TABLE IF NOT EXISTS schema_migrations (
                    migration TEXT PRIMARY KEY,
                    checksum TEXT NOT NULL,
                    applied_at TEXT NOT NULL
                )
                SQL,
        );
    }

    /**
     * Upgrade the old two-column ledger without dropping migration history.
     *
     * @return void No return value.
     * @throws RuntimeException If metadata inspection fails.
     */
    private function upgradeLegacyLedger() : void
    {
        $statement = $this->pdo->query(query: 'PRAGMA table_info(schema_migrations)');
        if ($statement === false) {
            throw new RuntimeException(message: 'Cannot inspect migration metadata.');
        }

        foreach ($statement->fetchAll(mode: PDO::FETCH_ASSOC) as $column) {
            if (! is_array(value: $column)) {
                throw new RuntimeException(message: 'Invalid migration table metadata.');
            }

            if (($column['name'] ?? null) === 'checksum') {
                return;
            }
        }

        $this->pdo->exec(statement: 'ALTER TABLE schema_migrations ADD COLUMN checksum TEXT');
    }

    /**
     * Validate migration IDs and persisted hashes.
     *
     * @return array<string, string|null> Applied IDs to checksum or legacy null.
     * @throws RuntimeException If ledger metadata is invalid.
     */
    private function appliedMigrations() : array
    {
        $statement = $this->pdo->query(query: 'SELECT migration, checksum FROM schema_migrations');
        if ($statement === false) {
            throw new RuntimeException(message: 'Cannot read migration ledger.');
        }

        $applied = [];

        foreach ($statement->fetchAll(mode: PDO::FETCH_ASSOC) as $row) {
            if (! is_array(value: $row)) {
                throw new RuntimeException(message: 'Invalid migration ledger row.');
            }

            $id   = $row['migration'] ?? null;
            $hash = $row['checksum'] ?? null;
            if (! is_string(value: $id) || ($hash !== null && (! is_string(value: $hash) || strlen(string: $hash) !== 64))) {
                throw new RuntimeException(message: 'Invalid migration metadata.');
            }
            $applied[$id] = $hash;
        }

        return $applied;
    }

    /**
     * Discover SQL files under each owner and order identifiers deterministically.
     *
     * @return array<string, string> migration identifier => file path
     */
    private function discoverMigrations() : array
    {
        $migrations = [];

        foreach ($this->migrationDirectories as $owner => $directory) {
            if (! is_dir(filename: $directory)) {
                throw new RuntimeException(message: sprintf('Migration directory for %s does not exist: %s', $owner, $directory));
            }

            $files = glob(pattern: rtrim(string: $directory, characters: '/') . '/*.sql');

            if ($files === false) {
                throw new RuntimeException(message: 'Unable to enumerate migrations in: ' . $directory);
            }

            foreach ($files as $file) {
                $identifier              = $owner . ':' . basename(path: $file);
                $migrations[$identifier] = $file;
            }
        }

        ksort(array: $migrations, flags: SORT_STRING);

        return $migrations;
    }

    /**
     * Read exact source bytes for SQL execution and SHA-256 fingerprint.
     *
     * @param string $file Trusted migration source path.
     *
     * @return string Nonempty SQL source.
     * @throws RuntimeException If the source is missing or empty.
     */
    private function readSql(string $file) : string
    {
        $sql = file_get_contents(filename: $file);

        if ($sql === false || trim(string: $sql) === '') {
            throw new RuntimeException(message: 'Migration is empty or unreadable: ' . $file);
        }

        return $sql;
    }

}
