<?php

declare(strict_types=1);

namespace InterviewTask\Infrastructure\Database;

use PDO;
use RuntimeException;

final class Migrator
{
    /**
     * @param array<string, string> $migrationDirectories owner => absolute directory
     */
    public function __construct(
        private readonly PDO   $pdo,
        private readonly array $migrationDirectories,
    ) {}

    /**
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

    private function apply(string $identifier, string $file): void
    {
        $sql = file_get_contents(filename: $file);

        if ($sql === false || trim(string: $sql) === '') {
            throw new RuntimeException(message: 'Migration is empty or unreadable: ' . $file);
        }

        (new Transaction(pdo: $this->pdo))->run(operation: function () use ($identifier, $sql): void {
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
