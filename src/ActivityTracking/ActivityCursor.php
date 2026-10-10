<?php

declare(strict_types=1);

namespace InterviewTask\ActivityTracking;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

/**
 * Owner-private v1 cursor codec: filter binding, session HMAC and strict wire-format validation.
 *
 * The public statistics API owns navigation; this object owns only its opaque cursor contract.
 *
 * @package InterviewTask\ActivityTracking
 */
final readonly class ActivityCursor
{
    /**
     * SHA-256 fingerprint of the owner's canonical date, actor and action filters.
     *
     * @var string
     */
    private string $fingerprint;

    /**
     * Bind every cursor to its originating authenticated PHP session and validated filters.
     *
     * @param string          $key     Secret signing key from the current PHP session.
     * @param ActivityFilters $filters Validated activity query constraints.
     *
     * @throws InvalidArgumentException If a signing key is absent.
     * @throws JsonException If the canonical filter encoding fails.
     * @throws RuntimeException If encoding unexpectedly does not produce a string.
     */
    public function __construct(
        private string  $key,
        ActivityFilters $filters,
    )
    {
        if ($key === '') {
            throw new InvalidArgumentException(message: 'Missing cursor signing context.');
        }

        $json = json_encode(value: [
                                       $filters->date === null ? '' : $filters->date->value(),
                                       $filters->username ?? '',
                                       $filters->action === null ? '' : $filters->action->value,
                                   ], flags: JSON_THROW_ON_ERROR);

        if (! is_string(value: $json)) {
            throw new RuntimeException(message: 'Unable to encode activity cursor filters.');
        }

        $this->fingerprint = hash(algo: 'sha256', data: $json);
    }

    /**
     * Authenticate and decode a v1 position, rejecting tampering and mismatched session/filter contexts.
     *
     * @param string $cursor Untrusted URL cursor.
     *
     * @return array{time: string, id: int, direction: string} Verified keyset position.
     * @throws InvalidArgumentException When signature, encoding, context or position are invalid.
     */
    public function decode(string $cursor) : array
    {
        if (strlen(string: $cursor) > 768 || preg_match(pattern: '/\A[A-Za-z0-9_-]+\.[a-f0-9]{64}\z/', subject: $cursor) !== 1) {
            throw new InvalidArgumentException(message: 'Invalid activity cursor.');
        }

        [$body, $mac] = explode(separator: '.', string: $cursor, limit: 2);
        if (! hash_equals(known_string: hash_hmac(algo: 'sha256', data: $body, key: $this->key), user_string: $mac)) {
            throw new InvalidArgumentException(message: 'Invalid activity cursor.');
        }

        $decoded = base64_decode(string: strtr($body, '-_', '+/'), strict: true);
        if ($decoded === false) {
            throw new InvalidArgumentException(message: 'Invalid activity cursor.');
        }

        try {
            $value = json_decode(json: $decoded, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException(message: 'Invalid activity cursor.', previous: $exception);
        }

        if (! is_array(value: $value) || count(value: $value) !== 5
            || ($value['v'] ?? null) !== 1 || ($value['f'] ?? null) !== $this->fingerprint
            || ! is_string(value: $value['t'] ?? null)
            || ! is_int(value: $value['id'] ?? null) || $value['id'] < 1
            || ! in_array(needle: $value['d'] ?? null, haystack: ['before', 'after'], strict: true)
            || preg_match(pattern: '/\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}\+00:00\z/', subject: $value['t']) !== 1) {
            throw new InvalidArgumentException(message: 'Invalid activity cursor.');
        }

        return ['time' => $value['t'], 'id' => $value['id'], 'direction' => $value['d']];
    }

    /**
     * Generate exactly the existing v1 JSON/base64url/HMAC cursor.
     *
     * @param string $time      Stored UTC event timestamp.
     * @param int    $id        Positive event ID.
     * @param string $direction before or after navigation direction.
     *
     * @return string Opaque session/filter-bound cursor.
     * @throws JsonException When encoding the cursor fields fails.
     * @throws RuntimeException If encoding unexpectedly returns a non-string value.
     */
    public function encode(string $time, int $id, string $direction) : string
    {
        $json = json_encode(value: [
                                       'v' => 1,
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                't' => $time,
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                'id' => $id,
                                       'd' => $direction,
                                       'f' => $this->fingerprint,
                                   ], flags: JSON_THROW_ON_ERROR);

        if (! is_string(value: $json)) {
            throw new RuntimeException(message: 'Unable to encode activity cursor.');
        }

        $body = rtrim(string: strtr(base64_encode(string: $json), '+/', '-_'), characters: '=');
        $mac  = hash_hmac(algo: 'sha256', data: $body, key: $this->key);

        return $body . '.' . $mac;
    }
}
