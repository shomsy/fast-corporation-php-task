<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Infrastructure;

use InterviewTask\Infrastructure\Database\Migrator;
use InterviewTask\Infrastructure\Database\SqliteConnection;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * Regression checks for Migrator test, covering observable behavior and failure paths.
 *
 * @package InterviewTask\Tests\Infrastructure
 */
final class MigratorTest extends TestCase
{
    /**
     * Verify applies owner local migrations once through the public behavior.
     *
     * @return void No return value.
     */
    public function testAppliesOwnerLocalMigrationsOnce(): void
    {
        $root     = dirname(path: __DIR__, levels: 2);
        $pdo      = SqliteConnection::memory();
        $migrator = new Migrator(pdo: $pdo, migrationDirectories: [
            'Accounts'         => $root . '/src/Accounts/migrations',
            'ActivityTracking' => $root . '/src/ActivityTracking/migrations',
        ]);

        self::assertSame(
            [
                'Accounts:001_create_accounts.sql',
                'ActivityTracking:001_create_activity_events.sql',
                'ActivityTracking:002_activity_query_indexes.sql',
            ],
            $migrator->migrate(),
        );
        self::assertSame([], $migrator->migrate());

        $statement = $pdo->query(
            query: "SELECT name FROM sqlite_master WHERE type = 'table' AND name IN ('accounts', 'activity_events') ORDER BY name",
        );

        if ($statement === false) {
            self::fail('Unable to inspect migrated tables.');
        }

        self::assertSame(['accounts', 'activity_events'], $statement->fetchAll(mode: PDO::FETCH_COLUMN));
    }

    /**
     * Verify accounts username uniqueness is case insensitive through the public behavior.
     *
     * @return void No return value.
     */
    public function testAccountsUsernameUniquenessIsCaseInsensitive(): void
    {
        $pdo = $this->migratedDatabase();

        $statement = $pdo->prepare(
            query: 'INSERT INTO accounts (username, password_hash, role, created_at) VALUES (?, ?, ?, ?)',
        );

        $statement->execute(params: ['Alice', 'hash', 'user', '2026-10-07T00:00:00+00:00']);

        $this->expectException(PDOException::class);
        $statement->execute(params: ['alice', 'hash', 'user', '2026-10-07T00:00:01+00:00']);
    }

    /**
     * Migrated database according to the MigratorTest contract.
     *
     * @return PDO Result described by the native PHP return type.
     */
    private function migratedDatabase(): PDO
    {
        $root = dirname(path: __DIR__, levels: 2);
        $pdo  = SqliteConnection::memory();

        (new Migrator(pdo: $pdo, migrationDirectories: [
            'Accounts'         => $root . '/src/Accounts/migrations',
            'ActivityTracking' => $root . '/src/ActivityTracking/migrations',
        ]))->migrate();

        return $pdo;
    }

    /**
     * Verify activity schema rejects invalid action target combination through the public behavior.
     *
     * @return void No return value.
     */
    public function testActivitySchemaRejectsInvalidActionTargetCombination(): void
    {
        $pdo = $this->migratedDatabase();

        $statement = $pdo->prepare(
            query: 'INSERT INTO activity_events (actor_id, actor_label, action, target, occurred_at) VALUES (?, ?, ?, ?, ?)',
        );

        $this->expectException(PDOException::class);
        $statement->execute(params: [1, 'alice', 'button-click', 'page-a', '2026-10-07T00:00:00+00:00']);
    }
}
