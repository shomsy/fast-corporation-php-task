<?php

declare(strict_types=1);

namespace InterviewTask\Accounts;

use DomainException;

/**
 * Denied authentication or authorization at a business access boundary.
 *
 * @api
 * @package InterviewTask\Accounts
 */
final class AccessDenied extends DomainException {}
