<?php

declare(strict_types=1);

use InterviewTask\Infrastructure\Database\Migrator;
use InterviewTask\Infrastructure\Database\SqliteConnection;

require dirname(__DIR__) . '/vendor/autoload.php';

/** @var array{root: string, databasePath: string} $config */
$config = require dirname(__DIR__) . '/bootstrap/config.php';

$pdo      = SqliteConnection::open($config['databasePath']);
$migrator = new Migrator($pdo, [
    'Accounts'         => $config['root'] . '/src/Accounts/migrations',
    'ActivityTracking' => $config['root'] . '/src/ActivityTracking/migrations',
]);

$applied = $migrator->migrate();

if ($applied === []) {
    fwrite(STDOUT, "No pending migrations.\n");

    exit(0);
}

foreach ($applied as $migration) {
    fwrite(STDOUT, 'Applied ' . $migration . PHP_EOL);
}
