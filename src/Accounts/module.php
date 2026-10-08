<?php

declare(strict_types=1);

use InterviewTask\Accounts\AccessDenied;
use InterviewTask\Accounts\AccountLifecycle;
use InterviewTask\Accounts\CurrentAccount;
use InterviewTask\Accounts\Login\InvalidCredentials;
use InterviewTask\Accounts\Register\RegistrationRejected;
use InterviewTask\Accounts\RequireAdmin;
use InterviewTask\Accounts\RequireSignedIn;
use InterviewTask\Accounts\SignedInAccount;
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
