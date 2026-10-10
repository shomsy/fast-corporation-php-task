<?php

declare(strict_types=1);

namespace InterviewTask\Accounts\Credentials;

use DomainException;

/**
 * Reject new account names outside the ASCII identifier policy aligned with SQLite NOCASE.
 *
 * @package InterviewTask\Accounts\Credentials
 */
final class UsernameRejected extends DomainException {}
