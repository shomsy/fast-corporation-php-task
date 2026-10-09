<?php

declare(strict_types=1);

namespace InterviewTask\Infrastructure\Diagnostics;

use InvalidArgumentException;
use PDOException;
use Throwable;

/**
 * Append bounded security and runtime diagnostics independently of accepted product activity.
 *
 * @package InterviewTask\Infrastructure\Diagnostics
 */
final class SecurityEvents
{
    /**
     * Record an approved event name with a random correlation ID; never accept user payloads.
     *
     * @param string $event Trusted allowlisted security event category.
     * @param Throwable|null $error Optional server exception; only its type is logged.
     * @return string Unpredictable event correlation ID safe to expose in a response header.
     * @throws InvalidArgumentException When the event name is not defined.
     */
    public static function record(string $event, ?Throwable $error = null): string
    {
        $allowed = ['auth.login_denied', 'security.csrf_denied', 'security.admin_denied', 'runtime.unhandled', 'db.contention'];

        if (! in_array(needle: $event, haystack: $allowed, strict: true)) {
            throw new InvalidArgumentException(message: 'Unrecognized security event category.');
        }

        if ($error instanceof PDOException && in_array(needle: $error->errorInfo[1] ?? null, haystack: [5, 6, '5', '6'], strict: true)) {
            $event = 'db.contention';
        }

        $id = bin2hex(string: random_bytes(length: 12));
        $entry = [
            'schema'     => 1,
            'event'      => $event,
            'request_id' => $id,
            'utc'        => gmdate(format: 'Y-m-d\TH:i:s\Z'),
        ];

        if ($error !== null) {
            $entry['exception_class'] = $error::class;
        }

        // The event name and error class are server-derived; no credentials, query, URI or exception message.
        // Approved operational security log, not a forgotten debug statement.
        /** @noinspection ForgottenDebugOutputInspection */
        error_log(message: (string) json_encode(value: $entry, flags: JSON_THROW_ON_ERROR));

        return $id;
    }
}
