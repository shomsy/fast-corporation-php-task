<?php

declare(strict_types=1);

namespace InterviewTask\ActivityTracking;

use DateTimeZone;
use InvalidArgumentException;
use PDO;
use PDOException;
use Psr\Clock\ClockInterface;
use RuntimeException;

/**
 * Authoritative append-only history of accepted user actions. The caller supplies the actor's historical label. This
 * owner does not fetch identity or credentials from Accounts.
 *
 * @api
 * @package InterviewTask\ActivityTracking
 */
final readonly class ActivityLog
{
    /**
     * Provide the activity writer with SQLite and a deterministic clock.
     *
     * @param PDO            $pdo   SQLite PDO connection with configured driver settings.
     * @param ClockInterface $clock Wall-clock source used for deterministic timestamps.
     */
    public function __construct(
        private PDO            $pdo,
        private ClockInterface $clock,
    ) {}

    /**
     * Append only after account creation has succeeded within its transaction.
     *
     * @param int    $actorId    Stable numeric identity associated with the audit event.
     * @param string $actorLabel Historical username snapshot written alongside the event.
     *
     * @return void No return value.
     */
    public function recordRegistration(int $actorId, string $actorLabel) : void
    {
        $this->append(actorId: $actorId, actorLabel: $actorLabel, action: ActivityAction::Registration, target: null);
    }

    /**
     * Validate the actor snapshot and persist exactly one UTC event. This method does not manage the caller's
     * transaction boundary.
     *
     * @param int            $actorId    Stable numeric identity associated with the audit event.
     * @param string         $actorLabel Historical username snapshot written alongside the event.
     * @param ActivityAction $action     Canonical activity action used for validation and filtering.
     * @param string|null    $target     Canonical optional action target; null where not applicable.
     *
     * @return void No return value.
     * @throws InvalidArgumentException When a submitted value or filter violates the documented precondition.
     * @throws PDOException When the underlying SQLite operation fails.
     * @throws RuntimeException When insertion SQL cannot be prepared.
     */
    private function append(
        int            $actorId,
        string         $actorLabel,
        ActivityAction $action,
        string|null    $target,
    ) : void
    {
        if ($actorId < 1) {
            throw new InvalidArgumentException(message: 'Actor ID must be positive.');
        }

        if (trim(string: $actorLabel) === '') {
            throw new InvalidArgumentException(message: 'Actor label cannot be blank.');
        }

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
                                        'occurred_at' => $this->clock->now()->setTimezone(timezone: new DateTimeZone(timezone: 'UTC'))->format(format: DATE_ATOM),
                                    ]);
    }

    /**
     * Record a successful, auditable login outcome.
     *
     * @param int    $actorId    Stable numeric identity associated with the audit event.
     * @param string $actorLabel Historical username snapshot written alongside the event.
     *
     * @return void No return value.
     */
    public function recordLogin(int $actorId, string $actorLabel) : void
    {
        $this->append(actorId: $actorId, actorLabel: $actorLabel, action: ActivityAction::Login, target: null);
    }

    /**
     * Record logout intent before session revocation when storage is available.
     *
     * @param int    $actorId    Stable numeric identity associated with the audit event.
     * @param string $actorLabel Historical username snapshot written alongside the event.
     *
     * @return void No return value.
     */
    public function recordLogout(int $actorId, string $actorLabel) : void
    {
        $this->append(actorId: $actorId, actorLabel: $actorLabel, action: ActivityAction::Logout, target: null);
    }

    /**
     * Record a view using the canonical tracked-page target.
     *
     * @param int         $actorId    Stable numeric identity associated with the audit event.
     * @param string      $actorLabel Historical username snapshot written alongside the event.
     * @param TrackedPage $page       Page number or typed tracked-page target, as dictated by the parameter type.
     *
     * @return void No return value.
     */
    public function recordPageView(int $actorId, string $actorLabel, TrackedPage $page) : void
    {
        $this->append(actorId: $actorId, actorLabel: $actorLabel, action: ActivityAction::ViewPage, target: $page->value);
    }

    /**
     * Record an accepted interaction using the canonical button target.
     *
     * @param int           $actorId    Stable numeric identity associated with the audit event.
     * @param string        $actorLabel Historical username snapshot written alongside the event.
     * @param TrackedButton $button     Canonical tracked button target.
     *
     * @return void No return value.
     */
    public function recordButtonClick(int $actorId, string $actorLabel, TrackedButton $button) : void
    {
        $this->append(actorId: $actorId, actorLabel: $actorLabel, action: ActivityAction::ButtonClick, target: $button->value);
    }
}
