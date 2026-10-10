<?php

declare(strict_types=1);

/**
 * Authoritative machine-readable exported surface of the Accounts owner.
 *
 * Only listed PHP types are public across business boundaries; enforcement compares static references to this manifest.
 *
 * @package InterviewTask\Accounts
 */

use InterviewTask\Accounts\Access\AccessDenied;
use InterviewTask\Accounts\AccountLifecycle;
use InterviewTask\Accounts\Access\CurrentAccount;
use InterviewTask\Accounts\Login\InvalidCredentials;
use InterviewTask\Accounts\Register\RegistrationRejected;
use InterviewTask\Accounts\Access\RequireAdmin;
use InterviewTask\Accounts\Access\RequireSignedIn;
use InterviewTask\Accounts\Access\SignedInAccount;
use InterviewTask\Accounts\UsernameTaken;

return [
    'public' => [
        AccountLifecycle::class,
        CurrentAccount::class,
        AccessDenied::class,
        InvalidCredentials::class,
        RegistrationRejected::class,
        UsernameTaken::class,
        RequireSignedIn::class,
        RequireAdmin::class,
        SignedInAccount::class,
    ],
];
