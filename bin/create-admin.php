<?php

declare(strict_types=1);

/**
 * Trusted command-line administrator-account bootstrap.
 *
 * Reject invalid credentials through the shared password policy; no admin creation route exists in HTTP.
 *
 * @package InterviewTask\Accounts
 */

use InterviewTask\Accounts\Accounts;
use InterviewTask\Accounts\CreateAdmin\CreateAdmin;
use InterviewTask\Accounts\PasswordRejected;
use InterviewTask\Accounts\UsernameTaken;
use InterviewTask\Accounts\UsernameRejected;
use InterviewTask\Infrastructure\Database\SqliteConnection;
use InterviewTask\Infrastructure\Time\SystemClock;

require dirname(path: __DIR__) . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    fwrite(stream: STDERR, data: "Admin creation is CLI-only.\n");

    exit(1);
}

$arguments = $_SERVER['argv'] ?? [];

if (! is_array(value: $arguments)) {
    $arguments = [];
}

$username          = $arguments[1] ?? null;
$passwordFromStdin = ($arguments[2] ?? '') === '--password-stdin';

if (! is_string(value: $username) || $username === '' || (count(value: $arguments) !== 2 && ! (count(value: $arguments) === 3 && $passwordFromStdin))) {
    fwrite(stream: STDERR, data: "Usage: php bin/create-admin.php <username> [--password-stdin]\n");

    exit(2);
}

if (! $passwordFromStdin && ! stream_isatty(stream: STDIN)) {
    fwrite(stream: STDERR, data: "Use --password-stdin for non-interactive setup.\n");

    exit(2);
}

if (! $passwordFromStdin) {
    fwrite(stream: STDERR, data: 'Admin password: ');
    $terminalSettings = shell_exec(command: 'stty -g');

    if (! is_string(value: $terminalSettings) || trim(string: $terminalSettings) === '') {
        fwrite(stream: STDERR, data: "Unable to securely read password from terminal.\n");

        exit(2);
    }

    shell_exec(command: 'stty -echo');

    try {
        $password = fgets(stream: STDIN);
    } finally {
        shell_exec(command: 'stty ' . escapeshellarg(arg: trim(string: $terminalSettings)));
        fwrite(stream: STDERR, data: PHP_EOL);
    }
} else {
    $password = fgets(stream: STDIN);
}

$password = is_string(value: $password) ? rtrim(string: $password, characters: "\r\n") : '';

if ($password === '') {
    fwrite(stream: STDERR, data: "Password must not be empty.\n");

    exit(2);
}

/** @var array{databasePath: string} $config */
$config = require dirname(path: __DIR__) . '/bootstrap/config.php';

try {
    (new CreateAdmin(accounts: new Accounts(pdo: SqliteConnection::open(path: $config['databasePath'])), clock: new SystemClock()))
        ->create(username: $username, password: $password);
} catch (UsernameTaken) {
    fwrite(stream: STDERR, data: "Username is already taken.\n");

    exit(1);
} catch (PasswordRejected|UsernameRejected $error) {
    fwrite(stream: STDERR, data: $error->getMessage() . PHP_EOL);

    exit(2);
}

fwrite(stream: STDOUT, data: "Admin created.\n");
