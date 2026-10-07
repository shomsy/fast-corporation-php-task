<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$envFile = $root . '/.env';

if (is_file($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines === false) {
        throw new RuntimeException('Unable to read .env file.');
    }

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));

        if ($key === '' || getenv($key) !== false) {
            continue;
        }

        $value = trim($value, " \t\n\r\0\x0B\"'");

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}

$dbPath = getenv('DB_PATH');
$dbPath = is_string($dbPath) && $dbPath !== '' ? $dbPath : 'var/database/app.sqlite';

if (!str_starts_with($dbPath, '/')) {
    $dbPath = $root . '/' . $dbPath;
}

$debug = filter_var(getenv('APP_DEBUG') ?: '0', FILTER_VALIDATE_BOOL);
$secureCookie = filter_var(getenv('SESSION_COOKIE_SECURE') ?: '0', FILTER_VALIDATE_BOOL);

/**
 * @var array{
 *     root: string,
 *     environment: string,
 *     debug: bool,
 *     databasePath: string,
 *     sessionCookieSecure: bool
 * }
 */
return [
    'root' => $root,
    'environment' => getenv('APP_ENV') ?: 'local',
    'debug' => $debug,
    'databasePath' => $dbPath,
    'sessionCookieSecure' => $secureCookie,
];
