<?php

declare(strict_types=1);

use InterviewTask\Accounts\AccessDenied;
use InterviewTask\Accounts\AccountLifecycle;
use InterviewTask\Accounts\Login\InvalidCredentials;
use InterviewTask\Accounts\Login\Login;
use InterviewTask\Accounts\Logout\Logout;
use InterviewTask\Accounts\Register\Register;
use InterviewTask\Accounts\Register\RegistrationRejected;
use InterviewTask\Accounts\RequireAdmin;
use InterviewTask\Accounts\RequireSignedIn;
use InterviewTask\Accounts\SessionIdentity;
use InterviewTask\Accounts\SignedInAccount;
use InterviewTask\Accounts\UsernameTaken;

return [
    'public' => [
        Register::class,
        AccountLifecycle::class,
        AccessDenied::class,
        InvalidCredentials::class,
        RegistrationRejected::class,
        SessionIdentity::class,
        UsernameTaken::class,
        Login::class,
        Logout::class,
        RequireSignedIn::class,
        RequireAdmin::class,
        SignedInAccount::class,
    ],
];
