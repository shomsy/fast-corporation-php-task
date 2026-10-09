<?php

declare(strict_types=1);

namespace InterviewTask\ActivityTracking;

use InvalidArgumentException;

/**
 * Validated owner-level statistics predicates independent of SQL query construction.
 *
 * @package InterviewTask\ActivityTracking
 */
final readonly class ActivityFilters
{
    /**
     * Normalize optional filters with the same externally observable rules as Statistics.
     *
     * @param string|null $date   Exact UTC date; null or empty means unrestricted.
     * @param string|null $user   Case-insensitive exact actor label, trimmed.
     * @param string|null $action Canonical action value.
     *
     * @throws InvalidArgumentException On invalid date or unknown action.
     */
    public function __construct(
        string|null $date,
        string|null $user,
        string|null $action,
    )
    {
        $parsedDate = $date === null || $date === '' ? null : UtcDate::tryFrom(value: $date);
        if ($date !== null && $date !== '' && $parsedDate === null) {
            throw new InvalidArgumentException(message: 'Invalid date. Expected YYYY-MM-DD.');
        }

        $parsedAction = $action === null || $action === '' ? null : ActivityAction::tryFrom(value: $action);
        if ($action !== null && $action !== '' && $parsedAction === null) {
            throw new InvalidArgumentException(message: 'Unknown activity action.');
        }

        $this->date     = $parsedDate;
        $trimmed        = $user === null ? '' : trim(string: $user);
        $this->username = $trimmed === '' ? null : $trimmed;
        $this->action   = $parsedAction;
    }

    /**
     * Validated UTC calendar date, if supplied.
     *
     * @var UtcDate|null
     */
    public UtcDate|null $date;

    /**
     * Trimmed exact historical actor label, if supplied.
     *
     * @var string|null
     */
    public string|null $username;

    /**
     * Canonical validated action, if supplied.
     *
     * @var ActivityAction|null
     */
    public ActivityAction|null $action;
}
