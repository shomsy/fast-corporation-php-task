<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Accounts;

use InterviewTask\Accounts\AccountLifecycle;
use InterviewTask\Accounts\Accounts;
use InterviewTask\Accounts\Login\Login;
use InterviewTask\Accounts\Register\Register;
use InterviewTask\Accounts\SessionIdentity;
use InterviewTask\ActivityTracking\ActivityLog;
use InterviewTask\Infrastructure\Database\Migrator;
use InterviewTask\Infrastructure\Database\SqliteConnection;
use InterviewTask\Infrastructure\Database\Transaction;
use InterviewTask\Infrastructure\Session\PhpSession;
use InterviewTask\Infrastructure\Time\SystemClock;
use PDOException;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

/**
 * Isolate PHP's process-global session save handler to inject cleanup failure safely.
 *
 * @package InterviewTask\Tests\Accounts
 */
final class SessionCompensationTest extends TestCase
{
    /**
     * An unavailable activity table and failed native session-destroy must report the original database error.
     *
     * @return void No return value.
     */
    #[RunInSeparateProcess]
    public function testLogoutRetainsAuditFailureIfSessionDestroyAlsoFails(): void
    {
        ini_set(option: 'session.use_cookies', value: '0');
        $handler = new class extends \SessionHandler {
            /**
             * Enable injected failure only after successful authentication.
             *
             * @var bool
             */
            public bool $failDestroy = false;

            /**
             * Simulate a native session store failure on destroy.
             *
             * @param string $id Session identifier.
             * @return bool Native session destroy result when not faulted.
             */
            public function destroy(string $id): bool
            {
                if ($this->failDestroy) {
                    throw new RuntimeException(message: 'Secondary session-destroy failure.');
                }

                return parent::destroy(id: $id);
            }
        };
        self::assertTrue(session_set_save_handler($handler, true));

        $pdo = SqliteConnection::memory();
        (new Migrator(pdo: $pdo, migrationDirectories: [
            'Accounts' => dirname(path: __DIR__, levels: 2) . '/src/Accounts/migrations',
            'ActivityTracking' => dirname(path: __DIR__, levels: 2) . '/src/ActivityTracking/migrations',
        ]))->migrate();

        $accounts = new Accounts(pdo: $pdo);
        $clock = new SystemClock();
        $session = new PhpSession(name: 'cleanup-regression', secureCookie: false);
        $identity = new SessionIdentity(session: $session, accounts: $accounts, clock: $clock);
        $lifecycle = new AccountLifecycle(
            register: new Register(accounts: $accounts, clock: $clock),
            login: new Login(accounts: $accounts, identity: $identity),
            identity: $identity,
            activity: new ActivityLog(pdo: $pdo, clock: $clock),
            transaction: new Transaction(pdo: $pdo),
        );
        $lifecycle->register(username: 'cleanup-tester', password: 'strong-password');
        $lifecycle->login(username: 'cleanup-tester', password: 'strong-password');

        $pdo->exec(statement: 'DROP TABLE activity_events');
        $handler->failDestroy = true;

        try {
            $lifecycle->logout();
            self::fail('Both injected failures must not be ignored.');
        } catch (Throwable $failure) {
            self::assertInstanceOf(PDOException::class, $failure);
            self::assertSame([], $_SESSION, 'In-memory identity must be cleared before native cleanup.');
        } finally {
            $handler->failDestroy = false;
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
        }
    }
}
