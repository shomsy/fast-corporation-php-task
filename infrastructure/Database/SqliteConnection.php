<?php

declare(strict_types=1);

namespace InterviewTask\Infrastructure\Database;

use PDO;
use RuntimeException;

final class SqliteConnection
{
    public static function open(string $path): PDO
    {
        $directory = dirname(path: $path);

        if (! is_dir(filename: $directory) && ! mkdir(directory: $directory, permissions: 0775, recursive: true) && ! is_dir(filename: $directory)) {
            throw new RuntimeException(message: 'Unable to create database directory: ' . $directory);
        }

        return self::configure(pdo: new PDO(dsn: 'sqlite:' . $path));
    }

    private static function configure(PDO $pdo): PDO
    {
        $pdo->setAttribute(attribute: PDO::ATTR_ERRMODE, value: PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(attribute: PDO::ATTR_DEFAULT_FETCH_MODE, value: PDO::FETCH_ASSOC);
        $pdo->exec(statement: 'PRAGMA foreign_keys = ON');
        $pdo->exec(statement: 'PRAGMA busy_timeout = 5000');

        return $pdo;
    }

    public static function memory(): PDO
    {
        return self::configure(pdo: new PDO(dsn: 'sqlite::memory:'));
    }
}
