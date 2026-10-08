<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Accounts;

use DateTimeImmutable;
use DateTimeZone;
use InterviewTask\Accounts\Accounts;
use InterviewTask\Infrastructure\Database\Migrator;
use InterviewTask\Infrastructure\Database\SqliteConnection;
use InterviewTask\Infrastructure\Session\PhpSession;
use PDO;
use PHPUnit\Framework\TestCase;

abstract class AccountsTestCase extends TestCase
{
    protected PDO        $pdo;
    protected Accounts   $accounts;
    protected FixedClock $clock;

    #[\Override]

    protected function setUp(): void
    {
        parent::setUp();

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        $_SESSION = [];
        session_id(id: '');
        ini_set(option: 'session.use_cookies', value: '0');

        $this->pdo = SqliteConnection::memory();

        (new Migrator(pdo: $this->pdo, migrationDirectories: [
            'Accounts' => dirname(path: __DIR__, levels: 2) . '/src/Accounts/migrations',
        ]))->migrate();

        $this->accounts = new Accounts(pdo: $this->pdo);
        $this->clock    = new FixedClock(
            now: new DateTimeImmutable(datetime: '2026-10-08T00:00:00+00:00', timezone: new DateTimeZone(timezone: 'UTC')),
        );
    }

    #[\Override]

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        $_SESSION = [];
        session_id(id: '');

        parent::tearDown();
    }

    protected function session(): PhpSession
    {
        return new PhpSession(name: 'fast_task_test', secureCookie: false);
    }
}
