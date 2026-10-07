<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Infrastructure;

use InterviewTask\Infrastructure\Database\SqliteConnection;
use InterviewTask\Infrastructure\Database\Transaction;
use PDOException;
use PHPUnit\Framework\TestCase;

final class TransactionTest extends TestCase
{
    public function testRollsBackWhenOperationFails(): void
    {
        $pdo = SqliteConnection::memory();
        $pdo->exec('CREATE TABLE example (value TEXT NOT NULL)');

        $transaction = new Transaction($pdo);

        try {
            $transaction->run(static function () use ($pdo): void {
                $pdo->exec("INSERT INTO example (value) VALUES ('written')");
                $pdo->exec('INSERT INTO example (value) VALUES (NULL)');
            });
        } catch (PDOException) {
            // Expected SQLite constraint failure; transaction must roll back.
        }

        $statement = $pdo->query('SELECT COUNT(*) FROM example');

        if ($statement === false) {
            self::fail('Unable to inspect transaction result.');
        }

        self::assertSame(0, (int) $statement->fetchColumn());
    }
}
