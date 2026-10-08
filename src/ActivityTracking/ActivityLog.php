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

    public function recordRegistration(int $actorId, string $actorLabel): void
    {
        $this->append(actorId: $actorId, actorLabel: $actorLabel, action: ActivityAction::Registration, target: null);
    }

    private function append(
        int            $actorId,
        string         $actorLabel,
        ActivityAction $action,
        ?string        $target,
    ): void {
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

    public function recordLogin(int $actorId, string $actorLabel): void
    {
        $this->append(actorId: $actorId, actorLabel: $actorLabel, action: ActivityAction::Login, target: null);
    }

    public function recordLogout(int $actorId, string $actorLabel): void
    {
        $this->append(actorId: $actorId, actorLabel: $actorLabel, action: ActivityAction::Logout, target: null);
    }

    public function recordPageView(int $actorId, string $actorLabel, TrackedPage $page): void
    {
        $this->append(actorId: $actorId, actorLabel: $actorLabel, action: ActivityAction::ViewPage, target: $page->value);
    }

    public function recordButtonClick(int $actorId, string $actorLabel, TrackedButton $button): void
    {
        $this->append(actorId: $actorId, actorLabel: $actorLabel, action: ActivityAction::ButtonClick, target: $button->value);
    }
}
