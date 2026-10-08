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

final class RegisterTest extends AccountsTestCase
{
    public function testRegistrationCreatesUserAndStoresOnlyPasswordHash(): void
    {
        $account = (new Register($this->accounts, $this->clock))->register('  Alice  ', 'correct horse battery staple');

        self::assertSame('Alice', $account->username);
        self::assertSame(Role::User, $account->role);
        self::assertSame('2026-10-08T00:00:00+00:00', $account->createdAt->format('c'));

        $statement = $this->pdo->prepare('SELECT username, password_hash, role FROM accounts WHERE id = :id');
        $statement->execute(['id' => $account->id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        self::assertIsArray($row);
        self::assertSame('Alice', $row['username']);
        self::assertSame('user', $row['role']);
        self::assertNotSame('correct horse battery staple', $row['password_hash']);
        self::assertIsString($row['password_hash']);
        self::assertTrue(password_verify('correct horse battery staple', $row['password_hash']));
    }

    public function testDuplicateUsernameIsRejectedCaseInsensitively(): void
    {
        $register = new Register($this->accounts, $this->clock);
        $register->register('Alice', 'first password');

        $this->expectException(UsernameTaken::class);
        $register->register('alice', 'second password');
    }

    public function testNonUniqueSQLiteConstraintFailureIsNotReportedAsUsernameTaken(): void
    {
        $this->pdo->exec(<<<'SQL'
            CREATE TRIGGER accounts_reject_alice BEFORE INSERT ON accounts
            WHEN NEW.username = 'alice'
            BEGIN
                SELECT RAISE(ABORT, 'simulated CHECK constraint failure');
            END
            SQL);

        try {
            (new Register($this->accounts, $this->clock))->register('alice', 'safe-pass');
            self::fail('Other SQLite constraints must not masquerade as duplicate usernames.');
        } catch (PDOException $exception) {
            self::assertStringContainsString('simulated CHECK constraint failure', $exception->getMessage());
            self::assertNotSame('UNIQUE constraint failed: accounts.username', $exception->errorInfo[2] ?? null);
        }
    }

    #[DataProvider('invalidRegistrationProvider')]
    public function testBlankUsernameOrPasswordIsRejected(string $username, string $password): void
    {
        $this->expectException(RegistrationRejected::class);

        (new Register($this->accounts, $this->clock))->register($username, $password);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidRegistrationProvider(): iterable
    {
        yield 'blank username' => ['   ', 'password'];
        yield 'blank password' => ['alice', ''];
    }
}
