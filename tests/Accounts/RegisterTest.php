<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Accounts;

use InterviewTask\Accounts\Register\Register;
use InterviewTask\Accounts\Register\RegistrationRejected;
use InterviewTask\Accounts\Role;
use InterviewTask\Accounts\UsernameTaken;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Regression checks for Register test, covering observable behavior and failure paths.
 *
 * @package InterviewTask\Tests\Accounts
 */
final class RegisterTest extends AccountsTestCase
{
    /**
     * Invalid registration provider according to the RegisterTest contract.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function invalidRegistrationProvider(): iterable
    {
        yield 'blank username' => ['   ', 'password'];
        yield 'blank password' => ['alice', ''];
    }

    /**
     * Verify registration creates user and stores only password hash through the public behavior.
     *
     * @return void No return value.
     */
    public function testRegistrationCreatesUserAndStoresOnlyPasswordHash(): void
    {
        $account = (new Register(accounts: $this->accounts, clock: $this->clock))->register(username: '  Alice  ', password: 'correct horse battery staple');

        self::assertSame('Alice', $account->username);
        self::assertSame(Role::User, $account->role);
        self::assertSame('2026-10-08T00:00:00+00:00', $account->createdAt->format(format: 'c'));

        $statement = $this->pdo->prepare(query: 'SELECT username, password_hash, role FROM accounts WHERE id = :id');
        $statement->execute(params: ['id' => $account->id]);
        $row = $statement->fetch(mode: PDO::FETCH_ASSOC);

        self::assertIsArray($row);
        self::assertSame('Alice', $row['username']);
        self::assertSame('user', $row['role']);
        self::assertNotSame('correct horse battery staple', $row['password_hash']);
        self::assertIsString($row['password_hash']);
        self::assertTrue(password_verify(password: 'correct horse battery staple', hash: $row['password_hash']));
    }

    /**
     * Verify duplicate username is rejected case insensitively through the public behavior.
     *
     * @return void No return value.
     */
    public function testDuplicateUsernameIsRejectedCaseInsensitively(): void
    {
        $register = new Register(accounts: $this->accounts, clock: $this->clock);
        $register->register(username: 'Alice', password: 'first password');

        $this->expectException(UsernameTaken::class);
        $register->register(username: 'alice', password: 'second password');
    }

    /**
     * Verify non unique s q lite constraint failure is not reported as username taken through the public behavior.
     *
     * @return void No return value.
     */
    public function testNonUniqueSQLiteConstraintFailureIsNotReportedAsUsernameTaken(): void
    {
        $this->pdo->exec(
            statement: <<<'SQL'
                             CREATE TRIGGER accounts_reject_alice BEFORE INSERT ON accounts
                             WHEN NEW.username = 'alice'
                             BEGIN
                                 SELECT RAISE(ABORT, 'simulated CHECK constraint failure');
                             END
                             SQL,
        );

        try {
            (new Register(accounts: $this->accounts, clock: $this->clock))->register(username: 'alice', password: 'safe-pass');
            self::fail('Other SQLite constraints must not masquerade as duplicate usernames.');
        } catch (PDOException $exception) {
            self::assertStringContainsString('simulated CHECK constraint failure', $exception->getMessage());
            self::assertNotSame('UNIQUE constraint failed: accounts.username', $exception->errorInfo[2] ?? null);
        }
    }

    /**
     * Verify blank username or password is rejected through the public behavior.
     *
     * @param string $username Username submitted or persisted for the account.
     * @param string $password Secret credential; never log, echo, or include it in diagnostics.
     * @return void No return value.
     */
    #[DataProvider('invalidRegistrationProvider')]
    public function testBlankUsernameOrPasswordIsRejected(string $username, string $password): void
    {
        $this->expectException(RegistrationRejected::class);

        (new Register(accounts: $this->accounts, clock: $this->clock))->register(username: $username, password: $password);
    }
}
