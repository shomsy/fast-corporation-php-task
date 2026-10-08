<?php

declare(strict_types=1);

/**
 * Trusted configuration and deployment boundary.
 *
 * Resolve paths and cookie security requirements without taking authority from browser input.
 *
 * @package InterviewTask\Bootstrap
 */

$root    = dirname(path: __DIR__);
$envFile = $root . '/.env';

if (is_file(filename: $envFile)) {
    $lines = file(filename: $envFile, flags: FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines === false) {
        throw new RuntimeException(message: 'Unable to read .env file.');
    }

    foreach ($lines as $line) {
        $line = trim(string: $line);

        if ($line === '' || str_starts_with(haystack: $line, needle: '#') || ! str_contains(haystack: $line, needle: '=')) {
            continue;
        }

        [$key, $value] = array_map(callback: 'trim', array: explode(separator: '=', string: $line, limit: 2));

        if ($key === '' || getenv(name: $key) !== false) {
            continue;
        }

        $value = trim(string: $value, characters: " \t\n\r\0\x0B\"'");

        putenv(assignment: $key . '=' . $value);
        $_ENV[$key] = $value;
    }
}

$dbPath = getenv(name: 'DB_PATH');
$dbPath = is_string(value: $dbPath) && $dbPath !== '' ? $dbPath : 'var/database/app.sqlite';

if (! str_starts_with(haystack: $dbPath, needle: '/')) {
    $dbPath = $root . '/' . $dbPath;
}

$debug        = filter_var(value: getenv(name: 'APP_DEBUG') ?: '0', filter: FILTER_VALIDATE_BOOL);
$secureCookie = filter_var(value: getenv(name: 'SESSION_COOKIE_SECURE') ?: '0', filter: FILTER_VALIDATE_BOOL);

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
    'root'                => $root,
    'environment'         => getenv(name: 'APP_ENV') ?: 'local',
    'debug'               => $debug,
    'databasePath'        => $dbPath,
    'sessionCookieSecure' => $secureCookie,
];
