<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Infrastructure;

use InterviewTask\Infrastructure\Database\SqliteConnection;
use InterviewTask\Infrastructure\Database\Transaction;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * Regression checks for Transaction test, covering observable behavior and failure paths.
 *
 * @package InterviewTask\Tests\Infrastructure
 */
final class TransactionTest extends TestCase
{
    /**
     * Verify rolls back when operation fails through the public behavior.
     *
     * @return void No return value.
     */
    public function testRollsBackWhenOperationFails(): void
    {
        $pdo = SqliteConnection::memory();
        $pdo->exec(statement: 'CREATE TABLE example (value TEXT NOT NULL)');

        $transaction = new Transaction(pdo: $pdo);

        try {
            $transaction->run(operation: static function () use ($pdo): void {
                $pdo->exec(statement: "INSERT INTO example (value) VALUES ('written')");
                $pdo->exec(statement: 'INSERT INTO example (value) VALUES (NULL)');
            });
        } catch (PDOException) {
            // Expected SQLite constraint failure; transaction must roll back.
        }

        $statement = $pdo->query(query: 'SELECT COUNT(*) FROM example');

        if ($statement === false) {
            self::fail('Unable to inspect transaction result.');
        }

        self::assertSame(0, (int) $statement->fetchColumn());
    }
}
