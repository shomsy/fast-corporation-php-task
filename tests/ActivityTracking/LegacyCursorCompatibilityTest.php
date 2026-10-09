<?php

declare(strict_types=1);

namespace InterviewTask\Tests\ActivityTracking;

use InterviewTask\ActivityTracking\Persistence\ReadActivityHistory;
use InterviewTask\ActivityTracking\Statistics;
use InterviewTask\Infrastructure\Database\Migrator;
use InterviewTask\Infrastructure\Database\SqliteConnection;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Lock the exact previously issued session cursor bytes across internal SQL refactoring.
 *
 * @package InterviewTask\Tests\ActivityTracking
 */
final class LegacyCursorCompatibilityTest extends TestCase
{
    /**
     * Ensure a pre-refactor signed cursor still matches and traverses the unchanged SQLite rows.
     *
     * @return void No return value.
     */
    public function testOriginalSignedCursorEncodingAndTraversalRemainByteCompatible() : void
    {
        $pdo = SqliteConnection::memory();
        (new Migrator(pdo: $pdo, migrationDirectories: [
            'ActivityTracking' => dirname(path: __DIR__, levels: 2) . '/src/ActivityTracking/migrations',
        ]))->migrate();
        $sql    = "INSERT INTO activity_events (actor_id, actor_label, action, target, occurred_at) VALUES (1, 'alice', 'view-page', 'page-a', '2026-10-09T12:00:00+00:00')";
        $insert = $pdo->prepare(query: $sql);
        self::assertNotFalse($insert);
        for ($i = 0; $i < 61; $i++) {
            self::assertTrue($insert->execute());
        }

        $statistics = new Statistics(reader: new ReadActivityHistory(pdo: $pdo));
        $key        = 'known-session-secret';
        $first      = $statistics->cursorPage(key: $key, date: '2026-10-09', user: '  alice  ', action: 'view-page');
        self::assertCount(Statistics::PAGE_SIZE, $first['events']);

        // Rebuild the old v1 wire encoding without calling the code under test.
        $fingerprint  = hash(algo: 'sha256', data: json_encode(value: ['2026-10-09', 'alice', 'view-page'], flags: JSON_THROW_ON_ERROR));
        $json         = json_encode(value: [
                                               'v' => 1, 't' => '2026-10-09T12:00:00+00:00', 'id' => 12, 'd' => 'before', 'f' => $fingerprint,
                                           ], flags: JSON_THROW_ON_ERROR);
        $encoded      = rtrim(string: strtr(base64_encode(string: $json), '+/', '-_'), characters: '=');
        $legacyCursor = $encoded . '.' . hash_hmac(algo: 'sha256', data: $encoded, key: $key);

        self::assertSame($legacyCursor, $first['nextCursor']);
        $second = $statistics->cursorPage(cursor: $legacyCursor, key: $key, date: '2026-10-09', user: 'alice', action: 'view-page');
        self::assertCount(11, $second['events']);
        self::assertSame(11, $second['events'][0]['id']);
        self::assertNull($second['nextCursor']);
    }

    /**
     * Malformed JSON with a valid session HMAC must not leak JsonException outside the cursor contract.
     *
     * @return void No return value.
     */
    public function testMalformedJsonWithValidHmacStillFailsAsInvalidCursor() : void
    {
        $pdo        = SqliteConnection::memory();
        $statistics = new Statistics(reader: new ReadActivityHistory(pdo: $pdo));
        $key        = 'a-valid-session-secret';
        $json       = '{"v":1';
        $body       = rtrim(string: strtr(base64_encode(string: $json), '+/', '-_'), characters: '=');
        $cursor     = $body . '.' . hash_hmac(algo: 'sha256', data: $body, key: $key);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid activity cursor.');
        $statistics->cursorPage(cursor: $cursor, key: $key);
    }

    /**
     * Omitted, null and empty signing contexts all fail with the documented domain exception.
     *
     * @return void No return value.
     */
    public function testMissingSigningContextFailsClosed() : void
    {
        $statistics = new Statistics(reader: new ReadActivityHistory(pdo: SqliteConnection::memory()));

        foreach ([
                     static fn () : array => $statistics->cursorPage(),
                     static fn () : array => $statistics->cursorPage(key: null),
                     static fn () : array => $statistics->cursorPage(key: ''),
                 ] as $call) {
            try {
                $call();
                self::fail('A cursor without a session signing key cannot be accepted.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame('Missing cursor signing context.', $exception->getMessage());
            }
        }
    }

}
