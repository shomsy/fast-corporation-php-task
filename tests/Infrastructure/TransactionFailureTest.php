<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Infrastructure;

use InterviewTask\Infrastructure\Database\SqliteConnection;
use InterviewTask\Infrastructure\Database\Transaction;
use LogicException;
use Override;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

/**
 * Failure injection for the one-owner transaction boundary.
 *
 * @package InterviewTask\Tests\Infrastructure
 */
final class TransactionFailureTest extends TestCase
{
    /**
     * Reject nested transactions without invoking the inner operation or destroying the caller's transaction.
     *
     * @return void No return value.
     */
    public function testNestedTransactionFailsWithoutTouchingOwnerTransaction() : void
    {
        $pdo = SqliteConnection::memory();
        $pdo->beginTransaction();
        $invoked = false;

        try {
            try {
                (new Transaction(pdo: $pdo))->run(operation: static function () use (&$invoked) : void {
                    $invoked = true;
                });
                self::fail('Nested transaction must be explicitly rejected.');
            } catch (LogicException $error) {
                self::assertSame('Nested transactions are not supported.', $error->getMessage());
            }
            self::assertFalse($invoked);
            self::assertTrue($pdo->inTransaction());
        } finally {
            $pdo->rollBack();
        }
    }

    /**
     * A failed rollback cannot mask an original business failure.
     *
     * @return void No return value.
     */
    public function testBusinessFailureSurvivesFailedRollback() : void
    {
        $pdo = new class('sqlite::memory:') extends PDO {
            /**
             * Force a secondary cleanup failure.
             *
             * @return bool Rollback is deliberately never completed.
             */
            #[Override]
            public function rollBack() : bool
            {
                throw new RuntimeException(message: 'secondary rollback failure');
            }
        };

        $original = new RuntimeException(message: 'primary business failure');
        try {
            (new Transaction(pdo: $pdo))->run(operation: static function () use ($pdo, $original) : void {
                if ($pdo->inTransaction()) {
                    throw $original;
                }
            });
            self::fail('Expected the original exception to survive.');
        } catch (Throwable $error) {
            self::assertSame($original, $error);
        }
    }

    /**
     * Commit failure is the primary failure even when subsequent cleanup also throws.
     *
     * @return void No return value.
     */
    public function testCommitFailureSurvivesFailedRollback() : void
    {
        $original = new RuntimeException(message: 'primary commit failure');

        $pdo = new class('sqlite::memory:', $original) extends PDO {
            /**
             * Simulate a failed commit while a transaction is still open.
             *
             * @param string           $dsn     SQLite memory DSN.
             * @param RuntimeException $failure Preselected primary error.
             */
            public function __construct(string $dsn, private RuntimeException $failure)
            {
                parent::__construct(dsn: $dsn);
            }

            /**
             * Force the primary commit error.
             *
             * @return bool Commit is deliberately refused.
             */
            #[Override]
            public function commit() : bool
            {
                throw $this->failure;
            }

            /**
             * Force secondary cleanup failure.
             *
             * @return bool Rollback is deliberately refused.
             */
            #[Override]
            public function rollBack() : bool
            {
                throw new RuntimeException(message: 'secondary rollback failure');
            }
        };

        try {
            (new Transaction(pdo: $pdo))->run(operation: static fn () : int => 1);
            self::fail('Expected commit failure.');
        } catch (Throwable $error) {
            self::assertSame($original, $error);
        }
    }
}
