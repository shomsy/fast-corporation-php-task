<?php

declare(strict_types=1);

namespace InterviewTask\Infrastructure\Database;

use InterviewTask\Infrastructure\Diagnostics\SecurityEvents;
use LogicException;
use PDO;
use Throwable;

/**
 * Own a PDO transaction and roll back failed operations without swallowing their cause.
 *
 * @package InterviewTask\Infrastructure\Database
 * @see     PDO
 */
final readonly class Transaction
{
    /**
     * Use a PDO connection whose transaction lifetime this object controls.
     *
     * @param PDO $pdo SQLite PDO connection with configured driver settings.
     */
    public function __construct(private PDO $pdo) {}

    /**
     * Commit the result of an operation or roll back and rethrow its original failure.
     *
     * @template T
     * @param callable(): T $operation
     *
     * @return T
     * @throws Throwable When the callback or commit fails; original error survives rollback errors.
     * @throws LogicException When called inside a transaction owned by another scope.
     */
    public function run(callable $operation) : mixed
    {
        if ($this->pdo->inTransaction()) {
            throw new LogicException(message: 'Nested transactions are not supported.');
        }

        $this->pdo->beginTransaction();

        try {
            $result = $operation();
            $this->pdo->commit();

            return $result;
        } catch (Throwable $throwable) {
            try {
                // We own this transaction. If a failed commit already ended it, the
                // rollback may throw too; preserve and report the primary failure.
                $this->pdo->rollBack();
            } catch (Throwable $cleanupError) {
                // Keep the root cause; log the secondary cleanup failure without SQL or secrets.
                try {
                    SecurityEvents::record(event: 'runtime.unhandled', error: $cleanupError);
                } catch (Throwable) {
                    // Diagnostic failure must not hide the original transaction failure.
                }
            }

            throw $throwable;
        }
    }
}
