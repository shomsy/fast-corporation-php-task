<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Accounts;

use InterviewTask\Accounts\Persistence\SqliteAccounts;
use InterviewTask\Accounts\Persistence\StoredCredentials;

/**
 * Keep the Accounts-to-Persistence credential boundary strongly typed.
 *
 * @package InterviewTask\Tests\Accounts
 */
final class StoredCredentialsTest extends AccountsTestCase
{
    /**
     * A retrieved account carries its hash without adding credentials to Account itself.
     *
     * @return void No return value.
     */
    public function testExistingAccountReturnsTypedStoredCredentials() : void
    {
        $account = $this->accounts->createUser(username: 'storeduser', password: 'strong-password', createdAt: $this->clock->now());
        $storage = new SqliteAccounts(pdo: $this->pdo);
        $stored  = $storage->credentialsFor(username: 'storeduser');
        self::assertInstanceOf(StoredCredentials::class, $stored);
        self::assertSame($account->id, $stored->account->id);
        self::assertSame($account->username, $stored->account->username);
        self::assertIsString($stored->hash);
        self::assertTrue(password_verify(password: 'strong-password', hash: $stored->hash));
        self::assertNull($storage->credentialsFor(username: 'absent-account'));
    }
}
