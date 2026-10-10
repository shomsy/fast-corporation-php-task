<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Flow;

use InterviewTask\Accounts\Accounts;
use InterviewTask\Accounts\AccountCredentials;
use InterviewTask\Accounts\BcryptPasswords;
use InterviewTask\Accounts\Persistence\SqliteAccounts;
use InterviewTask\Accounts\CurrentAccount;
use InterviewTask\Accounts\SessionIdentity;
use InterviewTask\Infrastructure\Diagnostics\CleanupFailures;
use Throwable;
use InterviewTask\Bootstrap\Http\SessionApi;
use InterviewTask\Infrastructure\Database\Migrator;
use InterviewTask\Infrastructure\Database\SqliteConnection;
use InterviewTask\Infrastructure\Session\Csrf;
use InterviewTask\Infrastructure\Session\PhpSession;
use InterviewTask\Infrastructure\Time\SystemClock;
use InterviewTask\Runtime\Http\Response;
use PHPUnit\Framework\TestCase;

/**
 * Verify the JSON transport exposes only the existing server-owned session contract.
 *
 * @package InterviewTask\Tests\Flow
 */
final class SessionApiTest extends TestCase
{
    /**
     * Anonymous bootstrap and authenticated state share server-side session and CSRF rotation.
     *
     * @return void No return value.
     */
    public function testJsonSessionAndCsrfRemainServerAuthoritative() : void
    {
        $pdo = SqliteConnection::memory();
        (new Migrator(pdo: $pdo, migrationDirectories: [
            'Accounts' => dirname(path: __DIR__, levels: 2) . '/src/Accounts/migrations',
        ]))->migrate();
        $session  = new PhpSession();
        $csrf     = new Csrf(session: $session);
        $accounts = new Accounts(storage: new SqliteAccounts(pdo: $pdo), credentials: new AccountCredentials(), bcrypt: new BcryptPasswords());
        $identity = new SessionIdentity(session: $session, accounts: $accounts, clock: new SystemClock(), cleanupFailures: new CleanupFailures(report: static function (Throwable $error) : void {}));
        $api      = new SessionApi(identity: new CurrentAccount(identity: $identity), csrf: $csrf);

        try {
            $guest = $api->session();
            self::assertSame(200, $guest->status());
            self::assertSame('application/json; charset=UTF-8', $guest->headers()['Content-Type']);
            self::assertSame('no-store', $guest->headers()['Cache-Control']);
            self::assertSame(['user' => null], json_decode(json: $guest->body(), associative: true, flags: JSON_THROW_ON_ERROR));

            $preLogin = json_decode(json: $api->csrf()->body(), associative: true, flags: JSON_THROW_ON_ERROR);
            self::assertIsArray($preLogin);
            self::assertIsString($preLogin['csrfToken']);
            self::assertMatchesRegularExpression('/\\A[a-f0-9]{64}\\z/D', $preLogin['csrfToken']);
            self::assertTrue($csrf->matches(submitted: $preLogin['csrfToken']));

            $account = $accounts->createUser(username: 'sessiontest', password: 'strong-password', createdAt: (new SystemClock())->now());
            $identity->signIn(account: $account);
            $active = json_decode(json: $api->session()->body(), associative: true, flags: JSON_THROW_ON_ERROR);
            self::assertSame([
                                 'user' => ['id' => $account->id, 'username' => 'sessiontest', 'role' => 'user'],
                             ], $active);
            self::assertStringNotContainsString('password', $api->session()->body());
            self::assertStringNotContainsString('csrfToken', $api->session()->body());

            $afterLogin = json_decode(json: $api->csrf()->body(), associative: true, flags: JSON_THROW_ON_ERROR);
            self::assertIsArray($afterLogin);
            self::assertIsString($afterLogin['csrfToken']);
            self::assertNotSame($preLogin['csrfToken'], $afterLogin['csrfToken']);
            self::assertFalse($csrf->matches(submitted: $preLogin['csrfToken']));
            self::assertTrue($csrf->matches(submitted: $afterLogin['csrfToken']));

            $identity->signOut();
            self::assertSame(['user' => null], json_decode(json: $api->session()->body(), associative: true, flags: JSON_THROW_ON_ERROR));
        } finally {
            $session->destroy();
        }
    }

    /**
     * JSON serialization must not embed executable HTML and must honor no-store policy.
     *
     * @return void No return value.
     */
    public function testJsonResponseHexEscapesUntrustedHtml() : void
    {
        $response = Response::json(payload: ['message' => '<script>alert("x")</script>']);
        self::assertSame(200, $response->status());
        self::assertStringNotContainsString('<script>', $response->body());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertSame(['message' => '<script>alert("x")</script>'], json_decode(json: $response->body(), associative: true, flags: JSON_THROW_ON_ERROR));
    }
}
