<?php

declare(strict_types=1);

namespace InterviewTask\Tests\CowOffer;

use InterviewTask\Accounts\Accounts;
use InterviewTask\Accounts\AccountCredentials;
use InterviewTask\Accounts\BcryptPasswords;
use InterviewTask\Accounts\Persistence\SqliteAccounts;
use InterviewTask\Accounts\Register\Register;
use InterviewTask\Accounts\RequireSignedIn;
use InterviewTask\Accounts\SessionIdentity;
use InterviewTask\Infrastructure\Diagnostics\CleanupFailures;
use Throwable;
use InterviewTask\ActivityTracking\ActivityLog;
use InterviewTask\ActivityTracking\Persistence\RecordActivity;
use InterviewTask\Bootstrap\Http\CowOfferApi;
use InterviewTask\CowOffer\CowOffer;
use InterviewTask\Infrastructure\Database\Migrator;
use InterviewTask\Infrastructure\Database\SqliteConnection;
use InterviewTask\Infrastructure\Session\PhpSession;
use InterviewTask\Infrastructure\Time\SystemClock;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * API view and click transport reuse the canonical CowOffer audit writer.
 *
 * @package InterviewTask\Tests\CowOffer
 */
final class CowOfferApiTest extends TestCase
{
    /**
     * View and repeated clicks count accepted actions while thankYou is nonpersistent.
     *
     * @return void No return value.
     */
    public function testRecordsAcceptedViewAndEachClick() : void
    {
        $pdo = SqliteConnection::memory();
        (new Migrator(pdo: $pdo, migrationDirectories: [
            'Accounts'         => dirname(path: __DIR__, levels: 2) . '/src/Accounts/migrations',
            'ActivityTracking' => dirname(path: __DIR__, levels: 2) . '/src/ActivityTracking/migrations',
        ]))->migrate();
        $session  = new PhpSession();
        $clock    = new SystemClock();
        $accounts = new Accounts(storage: new SqliteAccounts(pdo: $pdo), credentials: new AccountCredentials(), bcrypt: new BcryptPasswords());
        $identity = new SessionIdentity(session: $session, accounts: $accounts, clock: $clock, cleanupFailures: new CleanupFailures(report: static function (Throwable $error) : void {}));
        $api      = new CowOfferApi(
            cow     : new CowOffer(activityLog: new ActivityLog(writer: new RecordActivity(pdo: $pdo), clock: $clock)),
            signedIn: new RequireSignedIn(identity: $identity),
        );

        try {
            $person = (new Register(accounts: $accounts, clock: $clock))->register(
                username: 'cowuser', password: 'strong-password');
            $identity->signIn(account: $person);
            self::assertSame(204, $api->view()->status());
            for ($i = 0; $i < 2; $i++) {
                $response = $api->buy();
                self::assertSame(200, $response->status());
                self::assertSame(['outcome' => 'thankYou'], json_decode(
                    json: $response->body(), associative: true, flags: JSON_THROW_ON_ERROR));
            }

            $query = $pdo->query(query: 'SELECT action, target FROM activity_events ORDER BY id');
            self::assertNotFalse($query);
            self::assertSame([
                                 ['action' => 'view-page', 'target' => 'page-a'],
                                 ['action' => 'button-click', 'target' => 'buy-cow'],
                                 ['action' => 'button-click', 'target' => 'buy-cow'],
                             ], $query->fetchAll(mode: PDO::FETCH_ASSOC));
        } finally {
            $session->destroy();
        }
    }
}
