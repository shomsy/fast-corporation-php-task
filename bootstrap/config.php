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
$environment  = getenv(name: 'APP_ENV') ?: 'local';
$uiEntrypoint = getenv(name: 'UI_ENTRYPOINT');
$sessionPath  = getenv(name: 'SESSION_STORAGE_PATH');
$sessionPath  = is_string(value: $sessionPath) && $sessionPath !== '' ? $sessionPath : null;

if (! in_array(needle: $environment, haystack: ['local', 'test', 'production'], strict: true)) {
    throw new RuntimeException(message: 'Unsupported application environment.');
}

if ($uiEntrypoint !== false && $uiEntrypoint !== 'spa') {
    throw new RuntimeException(message: 'Unsupported UI entrypoint.');
}

if ($environment === 'production') {
    $workerUid = function_exists(function: 'posix_geteuid') ? posix_geteuid() : null;

    $isPrivate = static function (string $path, bool $directory) use ($workerUid) : bool {
        if (is_link(filename: $path) || ($directory ? ! is_dir(filename: $path) : ! is_file(filename: $path))) {
            return false;
        }
        $mode  = fileperms(filename: $path);
        $owner = fileowner(filename: $path);

        return is_int(value: $mode) && is_int(value: $owner)
            && ($workerUid === null || $owner === $workerUid)
            && ($mode & 0077) === 0
            && ($directory ? ($mode & 0700) === 0700 : (($mode & 0600) === 0600 && ($mode & 0100) === 0));
    };

    if ($sessionPath === null || ! str_starts_with(haystack: $sessionPath, needle: '/')
        || ! $isPrivate(path: $sessionPath, directory: true) || ! is_writable(filename: $sessionPath)) {
        throw new RuntimeException(message: 'Production requires preprovisioned private session storage.');
    }

    if (file_exists(filename: $envFile) && ! $isPrivate(path: $envFile, directory: false)) {
        throw new RuntimeException(message: 'Production environment file is not private.');
    }

    $databaseDirectory = dirname(path: $dbPath);
    if (is_dir(filename: $databaseDirectory) && ! $isPrivate(path: $databaseDirectory, directory: true)) {
        throw new RuntimeException(message: 'Production database directory is not private.');
    }

    foreach ([$dbPath, $dbPath . '-wal', $dbPath . '-shm'] as $databaseFile) {
        if (file_exists(filename: $databaseFile) && ! $isPrivate(path: $databaseFile, directory: false)) {
            throw new RuntimeException(message: 'Production database files are not private.');
        }
    }

    $appUrl = getenv(name: 'APP_URL');
    $parts  = is_string(value: $appUrl) ? parse_url(url: $appUrl) : false;

    if ($debug || ! $secureCookie || ! is_array(value: $parts)
        || ($parts['scheme'] ?? null) !== 'https' || ! is_string(value: $parts['host'] ?? null)
        || $parts['host'] === '') {
        throw new RuntimeException(message: 'Unsafe production configuration.');
    }
}

/**
 * @var array{
 *     root: string,
 *     environment: string,
 *     debug: bool,
 *     databasePath: string,
 *     sessionCookieSecure: bool,
 *     sessionStoragePath: string|null
 * }
 */
return [
    'root'                => $root,
    'environment'         => $environment,
    'debug'               => $debug,
    'databasePath'        => $dbPath,
    'sessionCookieSecure' => $secureCookie,
    'sessionStoragePath'  => $sessionPath,
];
