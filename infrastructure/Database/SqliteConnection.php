<?php

declare(strict_types=1);

namespace InterviewTask\Infrastructure\Database;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Open consistently configured SQLite connections for production and tests.
 *
 * @package InterviewTask\Infrastructure\Database
 */
final class SqliteConnection
{
    /**
     * Open existing database for runtime, allowing explicit CLI provisioning only.
     *
     * @param string $path            Trusted local SQLite filesystem path.
     * @param bool   $createIfMissing Explicit CLI migration provisioning permission.
     *
     * @return PDO Configured PDO connection to the requested SQLite file.
     * @throws RuntimeException If runtime database is absent or provisioning fails.
     * @throws PDOException If SQLite cannot be opened.
     */
    public static function open(string $path, bool $createIfMissing = false) : PDO
    {
        // SQLite creates WAL/SHM files lazily. Keep the whole worker/CLI process private.
        umask(mask: 0077);

        if (! $createIfMissing && ! is_file(filename: $path)) {
            throw new RuntimeException(message: 'Database has not been provisioned.');
        }

        if ($createIfMissing) {
            $directory = dirname(path: $path);

            if (! is_dir(filename: $directory) && ! mkdir(directory: $directory, permissions: 0700, recursive: true) && ! is_dir(filename: $directory)) {
                throw new RuntimeException(message: 'Unable to create database directory.');
            }
        }

        if (file_exists(filename: $path) && ! is_file(filename: $path)) {
            throw new RuntimeException(message: 'Configured database path is not a file.');
        }

        $pdo = self::configure(pdo: new PDO(dsn: 'sqlite:' . $path));

        if ($createIfMissing) {
            // Journal mode changes are provisioning decisions, never a web-request side effect.
            $mode = $pdo->query(query: 'PRAGMA journal_mode = WAL');

            if ($mode === false || strtolower(string: (string) $mode->fetchColumn()) !== 'wal') {
                throw new RuntimeException(message: 'Unable to enable SQLite WAL journal.');
            }
        }

        return $pdo;
    }

    /**
     * Apply strict error handling, associative fetches, foreign keys and a busy timeout.
     *
     * @param PDO $pdo SQLite PDO connection with configured driver settings.
     *
     * @return PDO The same PDO instance with connection invariants applied.
     */
    private static function configure(PDO $pdo) : PDO
    {
        $pdo->setAttribute(attribute: PDO::ATTR_ERRMODE, value: PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(attribute: PDO::ATTR_DEFAULT_FETCH_MODE, value: PDO::FETCH_ASSOC);
        $pdo->exec(statement: 'PRAGMA foreign_keys = ON');
        $pdo->exec(statement: 'PRAGMA busy_timeout = 5000');

        return $pdo;
    }

    /**
     * Open an isolated in-memory SQLite database with production connection settings.
     *
     * @return PDO Configured isolated in-memory PDO connection.
     */
    public static function memory() : PDO
    {
        return self::configure(pdo: new PDO(dsn: 'sqlite::memory:'));
    }
}
