<?php

declare(strict_types=1);

namespace InterviewTask\Accounts\Login;

use DomainException;

/**
 * Expected rejected-login outcome that reveals no credential details.
 *
 * @api
 * @package InterviewTask\Accounts\Login
 */
final class InvalidCredentials extends DomainException
{
    /**
     * Initialize dependencies required by this component.
     */
    public function __construct()
    {
        parent::__construct(message: 'Invalid username or password.');
    }
}
