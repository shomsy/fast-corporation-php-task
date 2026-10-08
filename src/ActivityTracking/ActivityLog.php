<?php

declare(strict_types=1);

namespace InterviewTask\ActivityTracking;

use DateTimeZone;
use InvalidArgumentException;
use PDO;
use Psr\Clock\ClockInterface;
use RuntimeException;

/**
 * Authoritative append-only history of accepted user actions.
 *
 * The caller supplies the actor's historical label. This owner does not
 * fetch identity or credentials from Accounts.
 */
final readonly class ActivityLog
{
    public function __construct(
        private PDO            $pdo,
        private ClockInterface $clock,
    ) {}

    public function recordRegistration(int $actorId, string $actorLabel) : void
    {
        $this->append($actorId, $actorLabel, ActivityAction::Registration, null);
    }

    private function append(
        int            $actorId,
        string         $actorLabel,
        ActivityAction $action,
        ?string        $target,
    ) : void
    {
        if ($actorId < 1) {
            throw new InvalidArgumentException('Actor ID must be positive.');
        }

        if (trim($actorLabel) === '') {
            throw new InvalidArgumentException('Actor label cannot be blank.');
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO activity_events (actor_id, actor_label, action, target, occurred_at)
             VALUES (:actor_id, :actor_label, :action, :target, :occurred_at)',
        );

        if ($statement === false) {
            throw new RuntimeException('Unable to prepare activity recording.');
        }

        $statement->execute([
                                'actor_id'    => $actorId,
                                'actor_label' => $actorLabel,
                                'action'      => $action->value,
                                'target'      => $target,
                                'occurred_at' => $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM),
                            ]);
    }

    public function recordLogin(int $actorId, string $actorLabel) : void
    {
        $this->append($actorId, $actorLabel, ActivityAction::Login, null);
    }

    public function recordLogout(int $actorId, string $actorLabel) : void
    {
        $this->append($actorId, $actorLabel, ActivityAction::Logout, null);
    }

    public function recordPageView(int $actorId, string $actorLabel, TrackedPage $page) : void
    {
        $this->append($actorId, $actorLabel, ActivityAction::ViewPage, $page->value);
    }

    public function recordButtonClick(int $actorId, string $actorLabel, TrackedButton $button) : void
    {
        $this->append($actorId, $actorLabel, ActivityAction::ButtonClick, $button->value);
    }
}
