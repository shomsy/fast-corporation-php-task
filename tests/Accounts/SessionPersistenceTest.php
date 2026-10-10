<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Accounts;

use InterviewTask\Accounts\AccountLifecycle;
use InterviewTask\Accounts\Login\Login;
use InterviewTask\Accounts\Register\Register;
use InterviewTask\Accounts\SessionIdentity;
use InterviewTask\ActivityTracking\ActivityLog;
use InterviewTask\ActivityTracking\Persistence\RecordActivity;
use InterviewTask\Infrastructure\Database\Migrator;
use InterviewTask\Infrastructure\Database\Transaction;
use InterviewTask\Infrastructure\Diagnostics\CleanupFailures;
use Override;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use RuntimeException;
use SessionHandler;
use Throwable;

/**
 * Login acceptance requires an observed successful session write, not only in-memory authentication.
 *
 * @package InterviewTask\Tests\Accounts
 */
#[RunTestsInSeparateProcesses]
final class SessionPersistenceTest extends AccountsTestCase
{
    /**
     * A failed native write cannot record an accepted login, even when compensation and diagnostics also fail.
     *
     * @param bool $cleanupFails Whether removal and its diagnostic destination also fail.
     *
     * @return void No return value.
     */
    #[DataProvider('cleanupAvailability')]
    public function testFailedSessionPersistencePreventsLoginAcceptance(bool $cleanupFails) : void
    {
        $storage = new class($cleanupFails) extends SessionHandler {
            /**
             * Whether native session writes are faulted.
             *
             * @var bool
             */
            public bool $failWrite = true;
            /**
             * Whether compensation is faulted after writing has failed.
             *
             * @var bool
             */
            public bool $failDestroy = false;

            /**
             * @param bool $cleanupFails Whether the failed write should also fault compensation.
             */
            public function __construct(private bool $cleanupFails) {}

            /**
             * Fail persistence only after authentication has been initialized.
             *
             * @param string $id   Native session identifier.
             * @param string $data Serialized session state.
             *
             * @return bool True only when native session bytes are written.
             */
            #[Override]
            public function write(string $id, string $data) : bool
            {
                if ($this->failWrite) {
                    $this->failDestroy = $this->cleanupFails;

                    return false;
                }

                return parent::write(id: $id, data: $data);
            }

            /**
             * Fail cleanup only after persistence failed; initial identifier rotation still works.
             *
             * @param string $id Native session identifier.
             *
             * @return bool Native removal result when available.
             * @throws RuntimeException When compensation storage is deliberately unavailable.
             */
            #[Override]
            public function destroy(string $id) : bool
            {
                if ($this->failDestroy) {
                    throw new RuntimeException(message: 'Secondary storage removal failure.');
                }

                return parent::destroy(id: $id);
            }
        };
        self::assertTrue(session_set_save_handler($storage, true));
        (new Migrator(pdo: $this->pdo, migrationDirectories: [
            'Accounts'         => dirname(path: __DIR__, levels: 2) . '/src/Accounts/migrations',
            'ActivityTracking' => dirname(path: __DIR__, levels: 2) . '/src/ActivityTracking/migrations',
        ]))->migrate();
        $reported    = [];
        $diagnostics = new CleanupFailures(report: static function (Throwable $error) use (&$reported) : void {
            $reported[] = $error;
            throw new RuntimeException(message: 'Diagnostic destination unavailable.');
        });
        $session     = $this->session();
        $identity    = new SessionIdentity(session: $session, accounts: $this->accounts, clock: $this->clock, cleanupFailures: $diagnostics);
        $lifecycle   = new AccountLifecycle(
            register       : new Register(accounts: $this->accounts, clock: $this->clock),
            login          : new Login(accounts: $this->accounts, identity: $identity),
            identity       : $identity,
            activity       : new ActivityLog(writer: new RecordActivity(pdo: $this->pdo), clock: $this->clock),
            transaction    : new Transaction(pdo: $this->pdo, cleanupFailures: $diagnostics),
            cleanupFailures: $diagnostics,
        );
        $lifecycle->register(username: 'alice', password: 'strong-password');

        try {
            try {
                $lifecycle->login(username: 'alice', password: 'strong-password');
                self::fail('An unpersisted session must not be accepted.');
            } catch (RuntimeException $failure) {
                self::assertSame('Unable to persist session.', $failure->getMessage());
            }

            self::assertSame([], $_SESSION);
            $events = $this->pdo->query(query: 'SELECT action FROM activity_events ORDER BY id');
            self::assertNotFalse($events);
            self::assertSame(['registration'], $events->fetchAll(mode: PDO::FETCH_COLUMN));
            self::assertCount($cleanupFails ? 1 : 0, $reported);
            if ($cleanupFails) {
                self::assertSame('Secondary storage removal failure.', $reported[0]->getMessage());
            }
        } finally {
            $storage->failWrite   = false;
            $storage->failDestroy = false;
        }
    }

    /**
     * Qualify both successful revocation and a second/third operational failure.
     *
     * @return iterable<string, array{bool}> Compensation scenarios.
     */
    public static function cleanupAvailability() : iterable
    {
        yield 'cleanup available' => [false];
        yield 'cleanup and diagnostics unavailable' => [true];
    }
}
