<?php

declare(strict_types=1);

namespace InterviewTask\Bootstrap\Http;

use InterviewTask\Infrastructure\Database\SqliteConnection;
use InterviewTask\Infrastructure\Diagnostics\SecurityEvents;
use InterviewTask\Runtime\Http\Request;
use InterviewTask\Runtime\Http\Response;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Liveness without dependencies and readiness with read-only schema evidence.
 *
 * Probes never create a session, invoke a business route or write an activity event.
 *
 * @package InterviewTask\Bootstrap\Http
 */
final class HealthProbes
{
    /**
     * Handle the two reserved operational paths before normal application bootstrap.
     *
     * @param Request $request Validated HTTP request.
     * @return Response|null Probe response, or null when the request is for a business route.
     */
    public static function respond(Request $request): ?Response
    {
        if (! in_array(needle: $request->path(), haystack: ['/healthz', '/readyz'], strict: true)) {
            return null;
        }

        if ($request->method() === 'OPTIONS') {
            return new Response(status: 204, headers: ['Allow' => 'GET, HEAD, OPTIONS']);
        }

        if (! in_array(needle: $request->method(), haystack: ['GET', 'HEAD'], strict: true)) {
            return new Response(body: 'Method Not Allowed', status: 405, headers: [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Allow'        => 'GET, HEAD, OPTIONS',
            ]);
        }

        if ($request->path() === '/healthz') {
            return Response::text(body: 'ok');
        }

        try {
            self::verifyReadiness();

            return Response::text(body: 'ready');
        } catch (Throwable $error) {
            // Keep dependency error details server-side; no user/session/account state.
            $id = SecurityEvents::record(event: 'runtime.unhandled', error: $error);

            return Response::text(body: 'Not Ready', status: 503)->withHeader(name: 'X-Request-ID', value: $id);
        }
    }

    /**
     * Verify deployed owner schema and migration hashes without applying DDL or writing data.
     *
     * @return void No return value.
     * @throws RuntimeException If the database or an expected migration is missing or modified.
     */
    private static function verifyReadiness(): void
    {
        /** @var array{databasePath: string, root: string} $config */
        $config = require dirname(path: __DIR__) . '/config.php';

        $pdo = SqliteConnection::open(path: $config['databasePath']);

        // Validate the columns actually needed for core login and activity reporting.
        foreach ([
            'SELECT id, username, password_hash, role FROM accounts LIMIT 1',
            'SELECT id, actor_id, action, target, occurred_at FROM activity_events LIMIT 1',
        ] as $sql) {
            $statement = $pdo->query(query: $sql);

            if ($statement === false) {
                throw new RuntimeException(message: 'Required application schema is unavailable.');
            }
        }

        $expected = [];

        foreach ([
            'Accounts' => $config['root'] . '/src/Accounts/migrations',
            'ActivityTracking' => $config['root'] . '/src/ActivityTracking/migrations',
        ] as $owner => $directory) {
            $files = glob(pattern: $directory . '/*.sql');

            if ($files === false || $files === []) {
                throw new RuntimeException(message: 'Migration source is unavailable.');
            }

            foreach ($files as $file) {
                $digest = hash_file(algo: 'sha256', filename: $file);

                if ($digest === false) {
                    throw new RuntimeException(message: 'Migration source is unreadable.');
                }

                $expected[$owner . ':' . basename(path: $file)] = $digest;
            }
        }

        $statement = $pdo->query(query: 'SELECT migration, checksum FROM schema_migrations');

        if ($statement === false) {
            throw new RuntimeException(message: 'Migration ledger is unavailable.');
        }

        $seen = 0;

        while (($row = $statement->fetch(mode: PDO::FETCH_ASSOC)) !== false) {
            $name = $row['migration'] ?? null;
            $checksum = $row['checksum'] ?? null;

            if (! is_string(value: $name) || ! is_string(value: $checksum)
                || ! isset($expected[$name])
                || ! hash_equals(known_string: $expected[$name], user_string: $checksum)) {
                throw new RuntimeException(message: 'Migration ledger and release disagree.');
            }

            ++$seen;
        }

        if ($seen !== count(value: $expected)) {
            throw new RuntimeException(message: 'Database migration set is incomplete.');
        }
    }
}
