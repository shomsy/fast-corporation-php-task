<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Infrastructure;

use Closure;
use InterviewTask\Accounts\Accounts;
use InterviewTask\Accounts\Access\RequireAdmin;
use InterviewTask\Accounts\Access\SessionIdentity;
use InterviewTask\Accounts\CreateAdmin\CreateAdmin;
use InterviewTask\Accounts\Credentials\AccountCredentials;
use InterviewTask\Accounts\Credentials\BcryptPasswords;
use InterviewTask\Accounts\Persistence\SqliteAccounts;
use InterviewTask\ActivityTracking\DailyReports;
use InterviewTask\ActivityTracking\Persistence\ReadActivityHistory;
use InterviewTask\ActivityTracking\Persistence\ReadDailyActivity;
use InterviewTask\ActivityTracking\Statistics;
use InterviewTask\Bootstrap\Http\ActivityApi;
use InterviewTask\Infrastructure\Database\Migrator;
use InterviewTask\Infrastructure\Database\SqliteConnection;
use InterviewTask\Infrastructure\Diagnostics\CleanupFailures;
use InterviewTask\Infrastructure\Session\Csrf;
use InterviewTask\Infrastructure\Session\PhpSession;
use InterviewTask\Infrastructure\Time\SystemClock;
use InterviewTask\Runtime\Http\Request;
use Override;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SessionHandler;
use Throwable;

/**
 * Session-backed tokens must be durable before their API can report success.
 *
 * @package InterviewTask\Tests\Infrastructure
 */
#[RunTestsInSeparateProcesses]
final class SessionConsumerPersistenceTest extends TestCase
{
    /**
     * A returned CSRF token is usable after the native request session is reopened.
     *
     * @return void No return value.
     */
    public function testNewCsrfTokenSurvivesNativeSessionReopen() : void
    {
        ini_set(option: 'session.use_cookies', value: '0');
        $session = new PhpSession();
        $csrf    = new Csrf(session: $session);

        try {
            $token = $csrf->token();
            // Discard subsequent in-memory changes instead of relying on shutdown to write.
            self::assertTrue(session_abort());
            self::assertTrue($csrf->matches(submitted: $token));
            self::assertSame($token, $csrf->token());
        } finally {
            $session->destroy();
        }
    }

    /**
     * Storage failure must prevent a new token from being returned to the browser.
     *
     * @return void No return value.
     */
    public function testNewCsrfTokenFailsBeforeIssuanceWhenNativeWriteFails() : void
    {
        $rejectWrites = $this->storage();
        $rejectWrites(true);
        $session = new PhpSession();
        $token   = null;

        try {
            try {
                $token = (new Csrf(session: $session))->token();
                self::fail('Unpersisted CSRF token must not be issued.');
            } catch (RuntimeException $error) {
                self::assertSame('Unable to persist session.', $error->getMessage());
                self::assertNull($token);
            }
        } finally {
            $rejectWrites(false);
            $session->destroy();
        }
    }

    /**
     * The first admin statistics response cannot expose a cursor bound to an unpersisted secret.
     *
     * @return void No return value.
     */
    public function testNewCursorSecretFailsBeforeStatisticsResponseWhenNativeWriteFails() : void
    {
        $rejectWrites = $this->storage();
        $pdo          = SqliteConnection::memory();
        (new Migrator(pdo: $pdo, migrationDirectories: [
            'Accounts'         => dirname(path: __DIR__, levels: 2) . '/src/Accounts/migrations',
            'ActivityTracking' => dirname(path: __DIR__, levels: 2) . '/src/ActivityTracking/migrations',
        ]))->migrate();
        $clock    = new SystemClock();
        $session  = new PhpSession();
        $accounts = new Accounts(storage: new SqliteAccounts(pdo: $pdo), credentials: new AccountCredentials(), bcrypt: new BcryptPasswords());
        $identity = new SessionIdentity(session        : $session, accounts: $accounts, clock: $clock,
                                        cleanupFailures: new CleanupFailures(report: static function (Throwable $error) : void {}));
        $api      = new ActivityApi(statistics: new Statistics(reader: new ReadActivityHistory(pdo: $pdo)),
                                    reports   : new DailyReports(reader: new ReadDailyActivity(pdo: $pdo)), admin: new RequireAdmin(identity: $identity), session: $session);

        try {
            $admin = (new CreateAdmin(accounts: $accounts, clock: $clock))->create(username: 'cursorfault', password: 'strong-password');
            $identity->signIn(account: $admin);
            $rejectWrites(true);
            $response = null;
            try {
                $response = $api->statistics(request: new Request(method: 'GET', path: '/api/activity/statistics'));
                self::fail('Statistics must not accept an unpersisted cursor secret.');
            } catch (RuntimeException $error) {
                self::assertSame('Unable to persist session.', $error->getMessage());
                self::assertNull($response);
            }
        } finally {
            $rejectWrites(false);
            $session->destroy();
        }
    }

    /**
     * Use native file storage normally, then inject only the meaningful write failure.
     *
     * @return Closure(bool): void Control write failure in the installed native-backed handler.
     */
    private function storage() : Closure
    {
        ini_set(option: 'session.use_cookies', value: '0');
        $storage = new class extends SessionHandler {
            /**
             * Reject subsequent native session writes when enabled.
             *
             * @var bool
             */
            public bool $rejectWrites = false;

            /**
             * @param string $id   Native session identifier.
             * @param string $data Encoded native session state.
             *
             * @return bool True after native persistence, false during the injected outage.
             */
            #[Override]
            public function write(string $id, string $data) : bool
            {
                return ! $this->rejectWrites && parent::write(id: $id, data: $data);
            }
        };
        self::assertTrue(session_set_save_handler($storage, true));

        return static function (bool $reject) use ($storage) : void {
            $storage->rejectWrites = $reject;
        };
    }
}
