<?php

declare(strict_types=1);

use InterviewTask\Accounts\Accounts;
use InterviewTask\Accounts\CreateAdmin\CreateAdmin;
use InterviewTask\Accounts\PasswordRejected;
use InterviewTask\Accounts\UsernameTaken;
use InterviewTask\Infrastructure\Database\SqliteConnection;
use InterviewTask\Infrastructure\Time\SystemClock;

require dirname(__DIR__) . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Admin creation is CLI-only.\n");

    exit(1);
}

$arguments = $_SERVER['argv'] ?? [];

if (!is_array($arguments)) {
    $arguments = [];
}

$username = $arguments[1] ?? null;
$passwordFromStdin = ($arguments[2] ?? '') === '--password-stdin';

if (!is_string($username) || $username === '' || (count($arguments) !== 2 && !(count($arguments) === 3 && $passwordFromStdin))) {
    fwrite(STDERR, "Usage: php bin/create-admin.php <username> [--password-stdin]\n");

    exit(2);
}

if (!$passwordFromStdin && !stream_isatty(STDIN)) {
    fwrite(STDERR, "Use --password-stdin for non-interactive setup.\n");

    exit(2);
}

if (!$passwordFromStdin) {
    fwrite(STDERR, 'Admin password: ');
    $terminalSettings = shell_exec('stty -g');

    if (!is_string($terminalSettings) || trim($terminalSettings) === '') {
        fwrite(STDERR, "Unable to securely read password from terminal.\n");

        exit(2);
    }

    shell_exec('stty -echo');

    try {
        $password = fgets(STDIN);
    } finally {
        shell_exec('stty ' . escapeshellarg(trim($terminalSettings)));
        fwrite(STDERR, PHP_EOL);
    }
} else {
    $password = fgets(STDIN);
}

$password = is_string($password) ? rtrim($password, "\r\n") : '';

if ($password === '') {
    fwrite(STDERR, "Password must not be empty.\n");

    exit(2);
}

/** @var array{databasePath: string} $config */
$config = require dirname(__DIR__) . '/bootstrap/config.php';

try {
    (new CreateAdmin(new Accounts(SqliteConnection::open($config['databasePath'])), new SystemClock()))
        ->create($username, $password);
} catch (UsernameTaken) {
    fwrite(STDERR, "Username is already taken.\n");

    exit(1);
} catch (PasswordRejected $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);

    exit(2);
}

fwrite(STDOUT, "Admin created.\n");
