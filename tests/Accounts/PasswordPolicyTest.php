<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Accounts;

use InterviewTask\Accounts\CreateAdmin\CreateAdmin;
use InterviewTask\Accounts\PasswordRejected;
use InterviewTask\Accounts\Register\Register;
use InterviewTask\Accounts\Register\RegistrationRejected;

final class PasswordPolicyTest extends AccountsTestCase
{
    public function testSeventyTwoBytePasswordIsValidButDistinctLongerPasswordCannotAuthenticate(): void
    {
        $password = str_repeat('a', 72);
        (new Register($this->accounts, $this->clock))->register('alice', $password);

        self::assertNotNull($this->accounts->authenticate('alice', $password));
        self::assertNull($this->accounts->authenticate('alice', $password . 'different suffix'));
    }

    public function testRegistrationRejectsOverlongUtf8PasswordsInBytesWithoutPersistingAccount(): void
    {
        $password = str_repeat('é', 37); // 74 UTF-8 bytes
        $this->expectException(RegistrationRejected::class);

        try {
            (new Register($this->accounts, $this->clock))->register('alice', $password);
        } finally {
            self::assertNull($this->accounts->authenticate('alice', $password));
            $query = $this->pdo->query('SELECT count(*) FROM accounts');
            self::assertNotFalse($query);
            self::assertSame(0, (int) $query->fetchColumn());
        }
    }

    public function testDirectAccountAndAdminCreationEnforceIdenticalPasswordBoundary(): void
    {
        $password = str_repeat('b', 73);

        try {
            $this->accounts->createUser('alice', $password, $this->clock->now());
            self::fail('Direct user creation should reject overlong passwords.');
        } catch (PasswordRejected) {
            self::assertNull($this->accounts->authenticate('alice', $password));
        }

        $this->expectException(PasswordRejected::class);
        (new CreateAdmin($this->accounts, $this->clock))->create('admin', $password);
    }
}
