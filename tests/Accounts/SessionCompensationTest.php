<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Accounts;

use Closure;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use InterviewTask\Accounts\AccountLifecycle;
use InterviewTask\Accounts\Accounts;
use InterviewTask\Accounts\Credentials\AccountCredentials;
use InterviewTask\Accounts\Credentials\BcryptPasswords;
use InterviewTask\Accounts\Persistence\SqliteAccounts;
use InterviewTask\Accounts\Login\Login;
use InterviewTask\Accounts\Register\Register;
use InterviewTask\Accounts\Access\SessionIdentity;
use InterviewTask\ActivityTracking\ActivityLog;
use InterviewTask\ActivityTracking\Persistence\RecordActivity;
use InterviewTask\Infrastructure\Database\Migrator;
use InterviewTask\Infrastructure\Database\SqliteConnection;
use InterviewTask\Infrastructure\Database\Transaction;
use InterviewTask\Infrastructure\Diagnostics\CleanupFailures;
use InterviewTask\Infrastructure\Session\PhpSession;
use InterviewTask\Infrastructure\Time\SystemClock;
use Override;
use PDOException;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\DataProvider;
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
     * @param string $failureOrigin   Login auditing, logout auditing or account lookup failure.
     * @param bool   $diagnosticsFail Whether the selected diagnostic sink also fails.
     *
     * @return void No return value.
     */
    #[RunInSeparateProcess]
    #[DataProvider('failureScenarios')]
    public function testPrimaryFailureSurvivesSessionCleanupAndDiagnosticFailures(string $failureOrigin, bool $diagnosticsFail) : void
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
             *
             * @return bool Native session destroy result when not faulted.
             */
            #[Override]
            public function destroy(string $id) : bool
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
            'Accounts'         => dirname(path: __DIR__, levels: 2) . '/src/Accounts/migrations',
            'ActivityTracking' => dirname(path: __DIR__, levels: 2) . '/src/ActivityTracking/migrations',
        ]))->migrate();

        $accounts        = new Accounts(storage: new SqliteAccounts(pdo: $pdo), credentials: new AccountCredentials(), bcrypt: new BcryptPasswords());
        $clock           = new SystemClock();
        $primary         = new RuntimeException(message: 'Primary login audit clock failure.');
        $auditClock      = new class($clock, $primary, static function () use ($handler) : void { $handler->failDestroy = true; }) implements ClockInterface {
            /**
             * Enable audit failure only after successful session establishment.
             *
             * @var bool
             */
            public bool $fail = false;

            /**
             * @param ClockInterface   $clock                Working time source.
             * @param RuntimeException $failure              Original audit failure.
             * @param Closure(): void  $enableCleanupFailure Activate a failure after login publishes its identity.
             */
            public function __construct(private ClockInterface $clock, private RuntimeException $failure, private Closure $enableCleanupFailure) {}

            /**
             * Fail auditing after authentication, preserving the primary failure object.
             *
             * @return DateTimeImmutable Current time when auditing is available.
             * @throws RuntimeException When auditing is deliberately failed.
             */
            #[Override]
            public function now() : DateTimeImmutable
            {
                if ($this->fail) {
                    ($this->enableCleanupFailure)();
                    throw $this->failure;
                }

                return $this->clock->now();
            }
        };
        $session         = new PhpSession(name: 'cleanup-regression', secureCookie: false);
        $reported        = [];
        $cleanupFailures = new CleanupFailures(report: static function (Throwable $error) use (&$reported, $diagnosticsFail) : void {
            $reported[] = $error;
            if ($diagnosticsFail) {
                throw new RuntimeException(message: 'Diagnostic destination unavailable.');
            }
        });
        $identity        = new SessionIdentity(session: $session, accounts: $accounts, clock: $clock, cleanupFailures: $cleanupFailures);
        $lifecycle       = new AccountLifecycle(
            register       : new Register(accounts: $accounts, clock: $clock),
            login          : new Login(accounts: $accounts, identity: $identity),
            identity       : $identity,
            activity       : new ActivityLog(writer: new RecordActivity(pdo: $pdo), clock: $auditClock),
            transaction    : new Transaction(pdo: $pdo, cleanupFailures: $cleanupFailures),
            cleanupFailures: $cleanupFailures,
        );
        $lifecycle->register(username: 'cleanup-tester', password: 'strong-password');
        if ($failureOrigin === 'login audit') {
            $auditClock->fail = true;
        } else {
            $lifecycle->login(username: 'cleanup-tester', password: 'strong-password');
            $pdo->exec(statement: $failureOrigin === 'logout lookup' ? 'DROP TABLE accounts' : 'DROP TABLE activity_events');
            $handler->failDestroy = true;
        }

        try {
            if ($failureOrigin === 'login audit') {
                $lifecycle->login(username: 'cleanup-tester', password: 'strong-password');
            } else {
                $lifecycle->logout();
            }
            self::fail('Both injected failures must not be ignored.');
        } catch (Throwable $failure) {
            if ($failureOrigin === 'login audit') {
                self::assertSame($primary, $failure);
            } else {
                self::assertInstanceOf(PDOException::class, $failure);
            }
            self::assertSame([], $_SESSION, 'In-memory identity must be cleared before native cleanup.');
            self::assertCount(1, $reported);
            self::assertSame('Secondary session-destroy failure.', $reported[0]->getMessage());
            if ($failureOrigin !== 'logout audit') {
                $events = $pdo->query(query: 'SELECT COUNT(*) FROM activity_events');
                self::assertNotFalse($events);
                self::assertSame($failureOrigin === 'login audit' ? 1 : 2, $events->fetchColumn(), 'Failed lifecycle outcomes must not add an accepted event.');
            }
        } finally {
            $handler->failDestroy = false;
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
        }
    }

    /**
     * Exercise audit and lookup failures with both working and unavailable diagnostics.
     *
     * @return iterable<string, array{string, bool}> Primary and tertiary failure scenarios.
     */
    public static function failureScenarios() : iterable
    {
        foreach (['login audit', 'logout audit', 'logout lookup'] as $origin) {
            yield $origin . ' / diagnostics available' => [$origin, false];
            yield $origin . ' / diagnostics unavailable' => [$origin, true];
        }
    }
}
