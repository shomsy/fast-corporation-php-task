<?php

declare(strict_types=1);

use InterviewTask\Accounts\Login\Login;
use InterviewTask\Accounts\Logout\Logout;
use InterviewTask\Accounts\Register\Register;
use InterviewTask\Accounts\RequireAdmin;
use InterviewTask\Accounts\RequireSignedIn;
use InterviewTask\Accounts\SignedInAccount;

return [
    'public' => [
        Register::class,
        Login::class,
        Logout::class,
        RequireSignedIn::class,
        RequireAdmin::class,
        SignedInAccount::class,
    ],
];
