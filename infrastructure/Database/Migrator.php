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
        private readonly PDO $pdo,
        private readonly array $migrationDirectories,
    ) {}

    /**
     * @return list<string> migration identifiers applied by this run
     */
    public function migrate(): array
    {
        $this->ensureMetadataTable();

        $applied = array_fill_keys($this->appliedMigrationIds(), true);
        $migrations = $this->discoverMigrations();
        $newlyApplied = [];

        foreach ($migrations as $identifier => $file) {
            if (isset($applied[$identifier])) {
                continue;
            }

            $this->apply($identifier, $file);
            $newlyApplied[] = $identifier;
        }

        return $newlyApplied;
    }

    private function ensureMetadataTable(): void
    {
        $this->pdo->exec(
            <<<'SQL'
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
        $statement = $this->pdo->query('SELECT migration FROM schema_migrations ORDER BY migration');

        if ($statement === false) {
            throw new RuntimeException('Unable to read applied migrations.');
        }

        $rows = $statement->fetchAll(PDO::FETCH_COLUMN);
        $migrations = [];

        foreach ($rows as $row) {
            if (!is_string($row)) {
                throw new RuntimeException('Migration identifier must be a string.');
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
            if (!is_dir($directory)) {
                throw new RuntimeException(sprintf('Migration directory for %s does not exist: %s', $owner, $directory));
            }

            $files = glob(rtrim($directory, '/') . '/*.sql');

            if ($files === false) {
                throw new RuntimeException('Unable to enumerate migrations in: ' . $directory);
            }

            foreach ($files as $file) {
                $identifier = $owner . ':' . basename($file);
                $migrations[$identifier] = $file;
            }
        }

        ksort($migrations, SORT_STRING);

        return $migrations;
    }

    private function apply(string $identifier, string $file): void
    {
        $sql = file_get_contents($file);

        if ($sql === false || trim($sql) === '') {
            throw new RuntimeException('Migration is empty or unreadable: ' . $file);
        }

        (new Transaction($this->pdo))->run(function () use ($identifier, $sql): void {
            $this->pdo->exec($sql);

            $statement = $this->pdo->prepare(
                'INSERT INTO schema_migrations (migration, applied_at) VALUES (:migration, :applied_at)',
            );

            $statement->execute([
                'migration' => $identifier,
                'applied_at' => gmdate('c'),
            ]);
        });
    }
}
