<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Infrastructure;

use InterviewTask\Infrastructure\Database\Migrator;
use InterviewTask\Infrastructure\Database\SqliteConnection;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

final class MigratorTest extends TestCase
{
    public function testAppliesOwnerLocalMigrationsOnce(): void
    {
        $root = dirname(__DIR__, 2);
        $pdo = SqliteConnection::memory();
        $migrator = new Migrator($pdo, [
            'Accounts' => $root . '/src/Accounts/migrations',
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
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name IN ('accounts', 'activity_events') ORDER BY name",
        );

        if ($statement === false) {
            self::fail('Unable to inspect migrated tables.');
        }

        self::assertSame(['accounts', 'activity_events'], $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public function testAccountsUsernameUniquenessIsCaseInsensitive(): void
    {
        $pdo = $this->migratedDatabase();

        $statement = $pdo->prepare(
            'INSERT INTO accounts (username, password_hash, role, created_at) VALUES (?, ?, ?, ?)',
        );

        $statement->execute(['Alice', 'hash', 'user', '2026-10-07T00:00:00+00:00']);

        $this->expectException(PDOException::class);
        $statement->execute(['alice', 'hash', 'user', '2026-10-07T00:00:01+00:00']);
    }

    public function testActivitySchemaRejectsInvalidActionTargetCombination(): void
    {
        $pdo = $this->migratedDatabase();

        $statement = $pdo->prepare(
            'INSERT INTO activity_events (actor_id, actor_label, action, target, occurred_at) VALUES (?, ?, ?, ?, ?)',
        );

        $this->expectException(PDOException::class);
        $statement->execute([1, 'alice', 'button-click', 'page-a', '2026-10-07T00:00:00+00:00']);
    }

    private function migratedDatabase(): PDO
    {
        $root = dirname(__DIR__, 2);
        $pdo = SqliteConnection::memory();

        (new Migrator($pdo, [
            'Accounts' => $root . '/src/Accounts/migrations',
            'ActivityTracking' => $root . '/src/ActivityTracking/migrations',
        ]))->migrate();

        return $pdo;
    }
}
