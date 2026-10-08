<?php

declare(strict_types=1);

namespace InterviewTask\Accounts;

use DomainException;
use Throwable;

/**
 * Expected duplicate username condition proven by the UNIQUE constraint.
 *
 * @api
 * @package InterviewTask\Accounts
 */
final class UsernameTaken extends DomainException
{
    /**
     * Initialize dependencies required by this component.
     *
     * @param Throwable|null $previous Input value for previous.
     */
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct(message: 'Username is already taken.', previous: $previous);
    }
}
