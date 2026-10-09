<?php

declare(strict_types=1);

/**
 * Make one verified, create-only SQLite online backup outside the web document root.
 *
 * Usage: php bin/backup.php /absolute/private/backup-directory/snapshot.sqlite
 *
 * @package InterviewTask\Infrastructure\Database
 */

if (PHP_SAPI !== 'cli') {
    throw new RuntimeException(message: 'Database backup is CLI-only.');
}

$arguments = $_SERVER['argv'] ?? null;

if (! is_array(value: $arguments) || count(value: $arguments) !== 2
    || ! is_string(value: $arguments[1] ?? null)
    || ! str_starts_with(haystack: $arguments[1], needle: '/')) {
    fwrite(stream: STDERR, data: "Usage: php bin/backup.php /absolute/private/directory/snapshot.sqlite\n");

    exit(2);
}

umask(mask: 0077);

/** @var array{databasePath: string} $config */
$config          = require dirname(path: __DIR__) . '/bootstrap/config.php';
$sourcePath      = $config['databasePath'];
$backupPath      = $arguments[1];
$backupDirectory = dirname(path: $backupPath);

if (! is_file(filename: $sourcePath) || is_link(filename: $sourcePath)) {
    throw new RuntimeException(message: 'Source database does not exist or is not a regular file.');
}

$mode  = is_dir(filename: $backupDirectory) ? fileperms(filename: $backupDirectory) : false;
$owner = is_dir(filename: $backupDirectory) ? fileowner(filename: $backupDirectory) : false;
$uid   = function_exists(function: 'posix_geteuid') ? posix_geteuid() : null;

if (is_link(filename: $backupDirectory) || ! is_int(value: $mode) || ! is_int(value: $owner)
    || ($uid !== null && $owner !== $uid)
    || ($mode & 0077) !== 0 || ($mode & 0700) !== 0700
    || ! is_writable(filename: $backupDirectory)) {
    throw new RuntimeException(message: 'Backup destination directory must be private and writable.');
}

if (file_exists(filename: $backupPath) || is_link(filename: $backupPath)) {
    throw new RuntimeException(message: 'Backup destination already exists.');
}

$tempPath     = $backupPath . '.pending-' . bin2hex(string: random_bytes(length: 8));
$source       = null;
$destination  = null;
$verification = null;

try {
    // SQLite online backup includes committed WAL data; copying only the .sqlite file does not.
    $source = new SQLite3(filename: $sourcePath, flags: SQLITE3_OPEN_READONLY);
    $source->busyTimeout(milliseconds: 5000);
    $destination = new SQLite3(filename: $tempPath, flags: SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);

    if (! $source->backup($destination)) {
        throw new RuntimeException(message: 'SQLite online backup failed.');
    }

    // The backup may inherit WAL mode. Confirm every committed page is in its main file.
    $checkpoint = $destination->querySingle(query: 'PRAGMA wal_checkpoint(TRUNCATE)', entireRow: true);
    if (! is_array(value: $checkpoint) || ($checkpoint['busy'] ?? null) !== 0) {
        throw new RuntimeException(message: 'SQLite backup WAL checkpoint did not complete.');
    }

    $destination->close();
    $destination = null;
    $source->close();
    $source = null;

    $verification = new SQLite3(filename: $tempPath, flags: SQLITE3_OPEN_READONLY);

    if ($verification->querySingle(query: 'PRAGMA integrity_check') !== 'ok') {
        throw new RuntimeException(message: 'SQLite backup integrity verification failed.');
    }

    $verification->close();
    $verification = null;

    if (! chmod(filename: $tempPath, permissions: 0600) || ! link(target: $tempPath, link: $backupPath)) {
        throw new RuntimeException(message: 'Unable to publish verified backup without overwriting existing data.');
    }

    unlink(filename: $tempPath);
    fwrite(stream: STDOUT, data: "SQLite backup integrity: PASS\n");
} finally {
    $verification?->close();
    $destination?->close();
    $source?->close();

    // Only discard sidecars belonging to this unique temporary snapshot, after close.
    foreach ([$tempPath . '-wal', $tempPath . '-shm', $tempPath] as $temporaryFile) {
        if (is_file(filename: $temporaryFile)) {
            unlink(filename: $temporaryFile);
        }
    }
}
