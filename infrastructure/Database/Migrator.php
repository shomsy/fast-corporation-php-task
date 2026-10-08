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
     * @param PDO $pdo SQLite PDO connection with configured driver settings.
     * @param array<string, string> $migrationDirectories owner => absolute directory
     */
    public function __construct(
        private readonly PDO   $pdo,
        private readonly array $migrationDirectories,
    ) {}

    /**
     * Apply new owner migrations once and return their identifiers.
     *
     * @return list<string> migration identifiers applied by this run
     */
    public function migrate(): array
    {
        $this->ensureMetadataTable();

        $applied      = array_fill_keys(keys: $this->appliedMigrationIds(), value: true);
        $migrations   = $this->discoverMigrations();
        $newlyApplied = [];

        foreach ($migrations as $identifier => $file) {
            if (isset($applied[$identifier])) {
                continue;
            }

            $this->apply(identifier: $identifier, file: $file);
            $newlyApplied[] = $identifier;
        }

        return $newlyApplied;
    }

    /**
     * Create the migration ledger without changing owner schema.
     *
     * @return void No return value.
     */
    private function ensureMetadataTable(): void
    {
        $this->pdo->exec(
            statement: <<<'SQL'
                CREATE TABLE IF NOT EXISTS schema_migrations (
                    migration TEXT PRIMARY KEY,
                    applied_at TEXT NOT NULL
                )
                SQL,
        );
    }

    /**
     * Read the migration ledger and validate every recorded identifier.
     *
     * @return list<string>
     */
    private function appliedMigrationIds(): array
    {
        $statement = $this->pdo->query(query: 'SELECT migration FROM schema_migrations ORDER BY migration');

        if ($statement === false) {
            throw new RuntimeException(message: 'Unable to read applied migrations.');
        }

        $rows       = $statement->fetchAll(mode: PDO::FETCH_COLUMN);
        $migrations = [];

        foreach ($rows as $row) {
            if (! is_string(value: $row)) {
                throw new RuntimeException(message: 'Migration identifier must be a string.');
            }

            $migrations[] = $row;
        }

        return $migrations;
    }

    /**
     * Discover SQL files under each owner and order identifiers deterministically.
     *
     * @return array<string, string> migration identifier => file path
     */
    private function discoverMigrations(): array
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
     * Execute one migration together with its ledger entry as one transaction.
     *
     * @param string $identifier Unique, owner-qualified migration identifier.
     * @param string $file Trusted SQL migration file path.
     * @return void No return value.
     * @throws \RuntimeException When a required external resource or stored value is invalid.
     */
    private function apply(string $identifier, string $file): void
    {
        $sql = file_get_contents(filename: $file);

        if ($sql === false || trim(string: $sql) === '') {
            throw new RuntimeException(message: 'Migration is empty or unreadable: ' . $file);
        }

        (new Transaction(pdo: $this->pdo))->run(operation: function () use ($sql, $identifier): void {
            $this->pdo->exec(statement: $sql);

            $statement = $this->pdo->prepare(
                query: 'INSERT INTO schema_migrations (migration, applied_at) VALUES (:migration, :applied_at)',
            );

            $statement->execute(params: [
                'migration'  => $identifier,
                'applied_at' => gmdate(format: 'c'),
            ]);
        });
    }
}
