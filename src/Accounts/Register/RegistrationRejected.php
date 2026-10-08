<?php

declare(strict_types=1);

namespace InterviewTask\Accounts\Register;

use DomainException;

/**
 * Expected registration validation failure shown to the submitting user.
 *
 * @api
 * @package InterviewTask\Accounts\Register
 */
final class RegistrationRejected extends DomainException {}
