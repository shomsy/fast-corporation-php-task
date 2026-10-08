<?php

declare(strict_types=1);

namespace InterviewTask\Infrastructure\Database;

use PDO;
use Throwable;

final readonly class Transaction
{
    public function __construct(private PDO $pdo) {}

    /**
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    public function run(callable $operation) : mixed
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
