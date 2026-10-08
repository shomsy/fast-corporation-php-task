<?php

declare(strict_types=1);

/**
 * Command-line database migration entry point.
 *
 * Apply the owner migrations to the configured database; command failures must be visible to the operator.
 *
 * @package InterviewTask\Infrastructure\Database
 */

use InterviewTask\Infrastructure\Database\Migrator;
use InterviewTask\Infrastructure\Database\SqliteConnection;

require dirname(path: __DIR__) . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    throw new RuntimeException(message: 'Database provisioning is CLI-only.');
}

/** @var array{root: string, databasePath: string} $config */
$config = require dirname(path: __DIR__) . '/bootstrap/config.php';

$pdo      = SqliteConnection::open(path: $config['databasePath'], createIfMissing: true);
$migrator = new Migrator(pdo: $pdo, migrationDirectories: [
    'Accounts'         => $config['root'] . '/src/Accounts/migrations',
    'ActivityTracking' => $config['root'] . '/src/ActivityTracking/migrations',
]);

$applied = $migrator->migrate();

if ($applied === []) {
    fwrite(stream: STDOUT, data: "No pending migrations.\n");

    exit(0);
}

foreach ($applied as $migration) {
    fwrite(stream: STDOUT, data: 'Applied ' . $migration . PHP_EOL);
}
