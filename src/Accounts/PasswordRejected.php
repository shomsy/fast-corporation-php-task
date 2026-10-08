<?php

declare(strict_types=1);

namespace InterviewTask\Accounts;

use RuntimeException;

/**
 * Rejected password that violates the Accounts credential policy.
 *
 * @package InterviewTask\Accounts
 */
final class PasswordRejected extends RuntimeException {}
