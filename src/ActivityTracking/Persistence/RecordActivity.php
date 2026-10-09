<?php

declare(strict_types=1);

namespace InterviewTask\ActivityTracking\Persistence;

use InterviewTask\ActivityTracking\ActivityAction;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Private SQLite writer for one canonical accepted activity event.
 *
 * @package InterviewTask\ActivityTracking\Persistence
 */
final readonly class RecordActivity
{
    /**
     * @param PDO $pdo The caller's configured SQLite connection and transaction.
     */
    public function __construct(private PDO $pdo) {}

    /**
     * Store the already-validated event without deciding its business meaning.
     *
     * @param int            $actorId    Positive actor identifier.
     * @param string         $actorLabel Historical account label.
     * @param ActivityAction $action     Owner-selected event action.
     * @param string|null    $target     Canonical action target if applicable.
     * @param string         $occurredAt UTC instant.
     *
     * @return void No return value.
     * @throws PDOException On SQLite execution failure.
     * @throws RuntimeException When SQLite cannot prepare the append.
     */
    public function append(int $actorId, string $actorLabel, ActivityAction $action, string|null $target, string $occurredAt) : void
    {
        $statement = $this->pdo->prepare(
            query: 'INSERT INTO activity_events (actor_id, actor_label, action, target, occurred_at)
             VALUES (:actor_id, :actor_label, :action, :target, :occurred_at)',
        );

        if ($statement === false) {
            throw new RuntimeException(message: 'Unable to prepare activity recording.');
        }

        $statement->execute(params: [
                                        'actor_id'    => $actorId,
                                        'actor_label' => $actorLabel,
                                        'action'      => $action->value,
                                        'target'      => $target,
                                        'occurred_at' => $occurredAt,
                                    ]);
    }
}
