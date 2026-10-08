<?php

declare(strict_types=1);

namespace InterviewTask\Infrastructure\Database;

use PDO;
use Throwable;

/**
 * Own a PDO transaction and roll back failed operations without swallowing their cause.
 *
 * @package InterviewTask\Infrastructure\Database
 * @see PDO
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
     * @return T
     * @throws Throwable When the transaction callback fails; the original failure is rethrown after rollback.
     */
    public function run(callable $operation): mixed
    {
        $this->pdo->beginTransaction();

        try {
            $result = $operation();
            $this->pdo->commit();

            return $result;
        } catch (Throwable $throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $throwable;
        }
    }
}
