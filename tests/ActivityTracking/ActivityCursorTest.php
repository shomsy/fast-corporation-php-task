<?php

declare(strict_types=1);

namespace InterviewTask\Tests\ActivityTracking;

use InterviewTask\ActivityTracking\ActivityCursor;
use InterviewTask\ActivityTracking\ActivityFilters;
use InvalidArgumentException;
use JsonException;
use PHPUnit\Framework\TestCase;

/**
 * Tests v1 session cursor behavior at the exact owner-private trust boundary.
 *
 * @package InterviewTask\Tests\ActivityTracking
 */
final class ActivityCursorTest extends TestCase
{
    /**
     * Confirm the wire token is byte-for-byte identical to the pre-extraction representation.
     *
     * @return void No return value.
     * @throws JsonException If the independent baseline serialization fails.
     */
    public function testOriginalV1TokenAndVerifiedPositionRemainUnchanged() : void
    {
        $key     = 'session-bound-secret';
        $filters = new ActivityFilters(date: '2026-10-09', user: '  Alice ', action: 'view-page');
        $codec   = new ActivityCursor(key: $key, filters: $filters);
        $actual  = $codec->encode(time: '2026-10-09T12:00:00+00:00', id: 73, direction: 'before');

        // Independent legacy serialization, not derived from the implementation under test.
        $fingerprint = hash(algo: 'sha256', data: json_encode(value: ['2026-10-09', 'Alice', 'view-page'], flags: JSON_THROW_ON_ERROR));
        $json        = json_encode(value: [
                                              'v' => 1, 't' => '2026-10-09T12:00:00+00:00', 'id' => 73,
                                              'd' => 'before', 'f' => $fingerprint,
                                          ], flags: JSON_THROW_ON_ERROR);
        self::assertIsString($json);
        $body = rtrim(string: strtr(base64_encode(string: $json), '+/', '-_'), characters: '=');
        self::assertSame($body . '.' . hash_hmac(algo: 'sha256', data: $body, key: $key), $actual);
        self::assertSame(
            ['time' => '2026-10-09T12:00:00+00:00', 'id' => 73, 'direction' => 'before'],
            $codec->decode(cursor: $actual),
        );
    }

    /**
     * A valid token must not be reusable with different canonical criteria or a different session.
     *
     * @return void No return value.
     */
    public function testTokenCannotCrossSessionOrFilterBoundary() : void
    {
        $criteria = new ActivityFilters(date: null, user: 'Alice', action: '');
        $token    = (new ActivityCursor(key: 'original-key', filters: $criteria))
            ->encode(time: '2026-10-09T12:00:00+00:00', id: 1, direction: 'after');

        foreach ([
                     new ActivityCursor(key: 'other-key', filters: $criteria),
                     new ActivityCursor(key: 'original-key', filters: new ActivityFilters(date: null, user: 'Bob', action: '')),
                 ] as $codec) {
            try {
                $codec->decode(cursor: $token);
                self::fail('A cursor must be bound to its session and filter context.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame('Invalid activity cursor.', $exception->getMessage());
            }
        }
    }

    /**
     * A correctly authenticated malformed JSON payload is rejected without leaking its parser exception.
     *
     * @return void No return value.
     */
    public function testMalformedSignedJsonRemainsInvalidCursor() : void
    {
        $key   = 'session-bound-secret';
        $body  = rtrim(string: strtr(base64_encode(string: '{"v":1'), '+/', '-_'), characters: '=');
        $token = $body . '.' . hash_hmac(algo: 'sha256', data: $body, key: $key);
        $codec = new ActivityCursor(key: $key, filters: new ActivityFilters(date: null, user: null, action: null));

        try {
            $codec->decode(cursor: $token);
            self::fail('Malformed signed JSON must be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Invalid activity cursor.', $exception->getMessage());
            self::assertInstanceOf(JsonException::class, $exception->getPrevious());
        }
    }

    /**
     * Empty filter spellings preserve their original v1 fingerprint equivalence.
     *
     * @return void No return value.
     */
    public function testNullAndEmptyFiltersUseTheSameOriginalFingerprint() : void
    {
        $nulls   = new ActivityCursor(key: 'session-bound-secret', filters: new ActivityFilters(date: null, user: null, action: null));
        $empties = new ActivityCursor(key: 'session-bound-secret', filters: new ActivityFilters(date: '', user: '  ', action: ''));

        self::assertSame(
            $nulls->encode(time: '2026-10-09T12:00:00+00:00', id: 5, direction: 'before'),
            $empties->encode(time: '2026-10-09T12:00:00+00:00', id: 5, direction: 'before'),
        );
    }
}
