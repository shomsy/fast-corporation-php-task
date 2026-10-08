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

    protected function setUp() : void
    {
        parent::setUp();

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        $_SESSION = [];
        session_id('');
        ini_set('session.use_cookies', '0');

        $this->pdo = SqliteConnection::memory();

        (new Migrator($this->pdo, [
            'Accounts' => dirname(__DIR__, 2) . '/src/Accounts/migrations',
        ]))->migrate();

        $this->accounts = new Accounts($this->pdo);
        $this->clock    = new FixedClock(
            new DateTimeImmutable('2026-10-08T00:00:00+00:00', new DateTimeZone('UTC')),
        );
    }

    protected function tearDown() : void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        $_SESSION = [];
        session_id('');

        parent::tearDown();
    }

    protected function session() : PhpSession
    {
        return new PhpSession('fast_task_test', false);
    }
}
