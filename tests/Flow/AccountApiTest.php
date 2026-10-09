<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Flow;

use InterviewTask\Accounts\AccountLifecycle;
use InterviewTask\Accounts\Accounts;
use InterviewTask\Accounts\Login\Login;
use InterviewTask\Accounts\Register\Register;
use InterviewTask\Accounts\SessionIdentity;
use InterviewTask\ActivityTracking\ActivityLog;
use InterviewTask\Bootstrap\Http\AccountApi;
use InterviewTask\Infrastructure\Database\Migrator;
use InterviewTask\Infrastructure\Database\SqliteConnection;
use InterviewTask\Infrastructure\Database\Transaction;
use InterviewTask\Infrastructure\Session\Csrf;
use InterviewTask\Infrastructure\Session\PhpSession;
use InterviewTask\Infrastructure\Time\SystemClock;
use InterviewTask\Runtime\Http\Request;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Reuse the original Accounts lifecycle through a JSON transport.
 *
 * @package InterviewTask\Tests\Flow
 */
final class AccountApiTest extends TestCase
{
    /**
     * Registration/login/logout preserve roles, CSRF rotation, and one event per accepted action.
     *
     * @return void No return value.
     */
    public function testAccountsApiReusesBusinessAndSessionAuthority(): void
    {
        $pdo = SqliteConnection::memory();
        (new Migrator(pdo: $pdo, migrationDirectories: [
            'Accounts' => dirname(path: __DIR__, levels: 2) . '/src/Accounts/migrations',
            'ActivityTracking' => dirname(path: __DIR__, levels: 2) . '/src/ActivityTracking/migrations',
        ]))->migrate();
        $clock = new SystemClock();
        $session = new PhpSession();
        $csrf = new Csrf(session: $session);
        $accounts = new Accounts(pdo: $pdo);
        $identity = new SessionIdentity(session: $session, accounts: $accounts, clock: $clock);
        $api = new AccountApi(lifecycle: new AccountLifecycle(
            register: new Register(accounts: $accounts, clock: $clock),
            login: new Login(accounts: $accounts, identity: $identity),
            identity: $identity,
            activity: new ActivityLog(pdo: $pdo, clock: $clock),
            transaction: new Transaction(pdo: $pdo),
        ), csrf: $csrf);

        try {
            $before = $csrf->token();
            self::assertSame(422, $api->register(request: $this->input('/api/accounts/register', '{"username":"x"}'))->status());
            self::assertSame(201, $api->register(request: $this->input('/api/accounts/register',
                '{"username":"alice","password":"strong-password","role":"admin"}'))->status());
            self::assertNull($identity->current());
            self::assertSame(409, $api->register(request: $this->input('/api/accounts/register',
                '{"username":"alice","password":"strong-password"}'))->status());
            self::assertSame(401, $api->login(request: $this->input('/api/accounts/login',
                '{"username":"alice","password":"wrong"}'))->status());

            $login = $api->login(request: $this->input('/api/accounts/login',
                '{"username":"alice","password":"strong-password"}'));
            self::assertSame(200, $login->status());
            $payload = json_decode(json: $login->body(), associative: true, flags: JSON_THROW_ON_ERROR);
            self::assertIsArray($payload);
            self::assertIsArray($payload['user'] ?? null);
            self::assertSame('user', $payload['user']['role']);
            self::assertSame('alice', $payload['user']['username']);
            self::assertNotSame($before, $payload['csrfToken']);
            self::assertFalse($csrf->matches(submitted: $before));
            self::assertTrue($csrf->matches(submitted: $payload['csrfToken']));
            self::assertSame(204, $api->logout()->status());
            self::assertNull($identity->current());

            $rows = $pdo->query(query: 'SELECT action FROM activity_events ORDER BY id');
            self::assertNotFalse($rows);
            self::assertSame(['registration', 'login', 'logout'], $rows->fetchAll(mode: PDO::FETCH_COLUMN));
        } finally {
            $session->destroy();
        }
    }

    /**
     * Create an injected raw HTTP JSON request for transport tests.
     *
     * @param string $path Absolute API path.
     * @param string $body Untrusted JSON bytes.
     * @return Request Test request.
     */
    private function input(string $path, string $body): Request
    {
        return new Request(method: 'POST', path: $path,
            server: ['CONTENT_TYPE' => 'application/json'], rawBody: $body);
    }
}
