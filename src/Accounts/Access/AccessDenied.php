<?php

declare(strict_types=1);

namespace InterviewTask\Accounts\Access;

use DomainException;

/**
 * Denied authentication or authorization at a business access boundary.
 *
 * @api
 * @package InterviewTask\Accounts\Access
 */
final class AccessDenied extends DomainException {}
