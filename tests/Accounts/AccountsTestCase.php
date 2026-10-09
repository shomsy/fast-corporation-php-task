<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Accounts;

use DateTimeImmutable;
use DateTimeZone;
use InterviewTask\Accounts\Accounts;
use InterviewTask\Accounts\AccountCredentials;
use InterviewTask\Accounts\BcryptPasswords;
use InterviewTask\Accounts\Persistence\SqliteAccounts;
use InterviewTask\Infrastructure\Database\Migrator;
use InterviewTask\Infrastructure\Database\SqliteConnection;
use InterviewTask\Infrastructure\Session\PhpSession;
use Override;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Shared Accounts test fixture with isolated SQLite and reset session state.
 *
 * @package InterviewTask\Tests\Accounts
 */
abstract class AccountsTestCase extends TestCase
{
    /**
     * Internal state or dependency owned by AccountsTestCase.
     *
     * @var PDO
     */
    protected PDO $pdo;
    /**
     * Internal state or dependency owned by AccountsTestCase.
     *
     * @var Accounts
     */
    protected Accounts $accounts;
    /**
     * Internal state or dependency owned by AccountsTestCase.
     *
     * @var FixedClock
     */
    protected FixedClock $clock;

    /**
     * Build isolated fixtures for this test case.
     *
     * @return void No return value.
     */
    #[Override]
    protected function setUp() : void
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

        $this->accounts = new Accounts(storage: new SqliteAccounts(pdo: $this->pdo), credentials: new AccountCredentials(), bcrypt: new BcryptPasswords());
        $this->clock    = new FixedClock(
            now: new DateTimeImmutable(datetime: '2026-10-08T00:00:00+00:00', timezone: new DateTimeZone(timezone: 'UTC')),
        );
    }

    /**
     * Release or reset test resources without leaking state.
     *
     * @return void No return value.
     */
    #[Override]
    protected function tearDown() : void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        $_SESSION = [];
        session_id(id: '');

        parent::tearDown();
    }

    /**
     * Session according to the AccountsTestCase contract.
     *
     * @return PhpSession Result described by the native PHP return type.
     */
    protected function session() : PhpSession
    {
        return new PhpSession(name: 'fast_task_test', secureCookie: false);
    }
}
