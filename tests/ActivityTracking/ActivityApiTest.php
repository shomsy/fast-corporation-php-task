<?php

declare(strict_types=1);

namespace InterviewTask\Tests\ActivityTracking;

use InterviewTask\Accounts\Accounts;
use InterviewTask\Accounts\AccountCredentials;
use InterviewTask\Accounts\BcryptPasswords;
use InterviewTask\Accounts\Persistence\SqliteAccounts;
use InterviewTask\Accounts\CreateAdmin\CreateAdmin;
use InterviewTask\Accounts\RequireAdmin;
use InterviewTask\Accounts\SessionIdentity;
use InterviewTask\ActivityTracking\DailyReports;
use InterviewTask\ActivityTracking\Persistence\ReadDailyActivity;
use InterviewTask\ActivityTracking\Statistics;
use InterviewTask\Bootstrap\Http\ActivityApi;
use InterviewTask\Infrastructure\Database\Migrator;
use InterviewTask\Infrastructure\Database\SqliteConnection;
use InterviewTask\Infrastructure\Session\PhpSession;
use InterviewTask\Infrastructure\Time\SystemClock;
use InterviewTask\Runtime\Http\Request;
use PHPUnit\Framework\TestCase;

/**
 * JSON admin analytics adapter contract with the same owner queries as SSR.
 *
 * @package InterviewTask\Tests\ActivityTracking
 */
final class ActivityApiTest extends TestCase
{
    /**
     * Signed filter cursor, 422 errors, and report counts must retain server authority.
     *
     * @return void No return value.
     */
    public function testAdminApiFiltersCursorAndReportCounts() : void
    {
        $pdo = SqliteConnection::memory();
        (new Migrator(pdo: $pdo, migrationDirectories: [
            'Accounts'         => dirname(path: __DIR__, levels: 2) . '/src/Accounts/migrations',
            'ActivityTracking' => dirname(path: __DIR__, levels: 2) . '/src/ActivityTracking/migrations',
        ]))->migrate();
        $session  = new PhpSession();
        $clock    = new SystemClock();
        $accounts = new Accounts(storage: new SqliteAccounts(pdo: $pdo), credentials: new AccountCredentials(), bcrypt: new BcryptPasswords());
        $identity = new SessionIdentity(session: $session, accounts: $accounts, clock: $clock);
        $api      = new ActivityApi(
            statistics: new Statistics(pdo: $pdo),
            reports   : new DailyReports(reader: new ReadDailyActivity(pdo: $pdo)),
            admin     : new RequireAdmin(identity: $identity),
            session   : $session,
        );

        try {
            $admin = (new CreateAdmin(accounts: $accounts, clock: $clock))->create(username: 'adminapi', password: 'strong-password');
            $identity->signIn(account: $admin);
            $insert = $pdo->prepare(query: "INSERT INTO activity_events(actor_id,actor_label,action,target,occurred_at) VALUES(1,'alpha','view-page','page-a','2026-10-07T12:00:00+00:00')");
            self::assertNotFalse($insert);
            for ($i = 0; $i < 51; $i++) {
                self::assertTrue($insert->execute());
            }

            $response = $api->statistics(request: new Request(method: 'GET', path: '/api/activity/statistics',
                                                              query : ['date' => '2026-10-07', 'action' => 'view-page']));
            self::assertSame(200, $response->status());
            $first = json_decode(json: $response->body(), associative: true, flags: JSON_THROW_ON_ERROR);
            self::assertIsArray($first);
            self::assertIsArray($first['events']);
            self::assertCount(50, $first['events']);
            self::assertIsString($first['nextCursor']);

            $next = $api->statistics(request: new Request(method: 'GET', path: '/api/activity/statistics',
                                                          query : ['date' => '2026-10-07', 'action' => 'view-page', 'cursor' => $first['nextCursor']]));
            $page = json_decode(json: $next->body(), associative: true, flags: JSON_THROW_ON_ERROR);
            self::assertIsArray($page);
            self::assertIsArray($page['events']);
            self::assertCount(1, $page['events']);
            self::assertNull($page['nextCursor']);

            $invalid = $api->statistics(request: new Request(method: 'GET', path: '/api/activity/statistics',
                                                             query : ['date' => '2026-02-30']));
            self::assertSame(422, $invalid->status());
            $crossFilter = $api->statistics(request: new Request(method: 'GET', path: '/api/activity/statistics',
                                                                 query : ['date' => '2026-10-08', 'action' => 'view-page', 'cursor' => $first['nextCursor']]));
            self::assertSame(422, $crossFilter->status());

            $report = $api->reports(request: new Request(method: 'GET', path: '/api/activity/reports',
                                                         query : ['from' => '2026-10-07', 'to' => '2026-10-07']));
            self::assertSame(200, $report->status());
            $daily = json_decode(json: $report->body(), associative: true, flags: JSON_THROW_ON_ERROR);
            self::assertIsArray($daily);
            self::assertSame([['date'         => '2026-10-07', 'pageAViews' => 51, 'pageBViews' => 0,
                               'buyCowClicks' => 0, 'downloadClicks' => 0]], $daily['days']);
            self::assertSame(422, $api->reports(request: new Request(method: 'GET',
                                                                     path  : '/api/activity/reports', query: ['from' => '2026-02-30']))->status());
        } finally {
            $session->destroy();
        }
    }
}
