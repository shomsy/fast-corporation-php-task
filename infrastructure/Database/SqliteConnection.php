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
     * Create the parent directory if necessary and open a file-backed SQLite database.
     *
     * @param string $path Absolute route, request path, or trusted local filesystem path.
     * @return PDO Configured PDO connection to the requested SQLite file.
     * @throws RuntimeException If the parent database directory cannot be created.
     * @throws PDOException If SQLite cannot be opened.
     */
    public static function open(string $path): PDO
    {
        $directory = dirname(path: $path);

        if (! is_dir(filename: $directory) && ! mkdir(directory: $directory, permissions: 0775, recursive: true) && ! is_dir(filename: $directory)) {
            throw new RuntimeException(message: 'Unable to create database directory: ' . $directory);
        }

        return self::configure(pdo: new PDO(dsn: 'sqlite:' . $path));
    }

    /**
     * Apply strict error handling, associative fetches, foreign keys and a busy timeout.
     *
     * @param PDO $pdo SQLite PDO connection with configured driver settings.
     * @return PDO The same PDO instance with connection invariants applied.
     */
    private static function configure(PDO $pdo): PDO
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
    public static function memory(): PDO
    {
        return self::configure(pdo: new PDO(dsn: 'sqlite::memory:'));
    }
}
