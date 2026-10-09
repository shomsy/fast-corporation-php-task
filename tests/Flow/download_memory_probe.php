<?php

declare(strict_types=1);

/**
 * Isolated PHP allocation probe for the fixed binary download owner.
 *
 * This is PHP allocated heap, not a PHP-FPM worker RSS or a public deployment SLO.
 *
 * @package InterviewTask\Tests\Flow
 */

use InterviewTask\ActivityTracking\ActivityLog;
use InterviewTask\Downloads\DownloadFile;
use InterviewTask\Infrastructure\Database\Migrator;
use InterviewTask\Infrastructure\Database\SqliteConnection;
use InterviewTask\Infrastructure\Time\SystemClock;

require dirname(path: __DIR__, levels: 2) . '/vendor/autoload.php';

$root = dirname(path: __DIR__, levels: 2);
$pdo = SqliteConnection::memory();
(new Migrator(pdo: $pdo, migrationDirectories: [
    'ActivityTracking' => $root . '/src/ActivityTracking/migrations',
]))->migrate();
$download = new DownloadFile(
    activityLog: new ActivityLog(pdo: $pdo, clock: new SystemClock()),
    artifactPath: $root . '/assets/sample.exe',
    expectedSha256: '31a97b0f71277adbac9bfde08c935c3e97b0bfa61d59c807c0a900660080cb80',
);
$bytes = $download->read(actorId: 1, actorLabel: 'probe');

fwrite(stream: STDOUT, data: (string) json_encode(value: [
    'body_bytes' => strlen(string: $bytes),
    'php_allocated_peak_bytes' => memory_get_peak_usage(real_usage: true),
    'max_artifact_bytes' => 2097152,
], flags: JSON_THROW_ON_ERROR) . PHP_EOL);
