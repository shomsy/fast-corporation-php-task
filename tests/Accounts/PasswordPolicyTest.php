<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Accounts;

use InterviewTask\Accounts\CreateAdmin\CreateAdmin;
use InterviewTask\Accounts\PasswordRejected;
use InterviewTask\Accounts\Register\Register;
use InterviewTask\Accounts\Register\RegistrationRejected;

/**
 * Regression checks for Password policy test, covering observable behavior and failure paths.
 *
 * @package InterviewTask\Tests\Accounts
 */
final class PasswordPolicyTest extends AccountsTestCase
{
    /**
     * Verify seventy two byte password is valid but distinct longer password cannot authenticate through the public behavior.
     *
     * @return void No return value.
     */
    public function testSeventyTwoBytePasswordIsValidButDistinctLongerPasswordCannotAuthenticate(): void
    {
        $password = str_repeat(string: 'a', times: 72);
        (new Register(accounts: $this->accounts, clock: $this->clock))->register(username: 'alice', password: $password);

        self::assertNotNull($this->accounts->authenticate(username: 'alice', password: $password));
        self::assertNull($this->accounts->authenticate(username: 'alice', password: $password . 'different suffix'));
    }

    /**
     * Verify registration rejects overlong utf8 passwords in bytes without persisting account through the public behavior.
     *
     * @return void No return value.
     */
    public function testRegistrationRejectsOverlongUtf8PasswordsInBytesWithoutPersistingAccount(): void
    {
        $password = str_repeat(string: 'é', times: 37); // 74 UTF-8 bytes
        $this->expectException(RegistrationRejected::class);

        try {
            (new Register(accounts: $this->accounts, clock: $this->clock))->register(username: 'alice', password: $password);
        } finally {
            self::assertNull($this->accounts->authenticate(username: 'alice', password: $password));
            $query = $this->pdo->query(query: 'SELECT count(*) FROM accounts');
            self::assertNotFalse($query);
            self::assertSame(0, (int) $query->fetchColumn());
        }
    }

    /**
     * Verify nul byte password is rejected across registration and admin creation through the public behavior.
     *
     * @return void No return value.
     */
    public function testNulBytePasswordIsRejectedAcrossRegistrationAndAdminCreation(): void
    {
        $password = "safe\0unsafe";

        try {
            (new Register(accounts: $this->accounts, clock: $this->clock))->register(username: 'alice', password: $password);
            self::fail('Registration must reject NUL bytes before bcrypt receives the password.');
        } catch (RegistrationRejected $exception) {
            self::assertStringContainsString('NUL', $exception->getMessage());
        }

        try {
            (new CreateAdmin(accounts: $this->accounts, clock: $this->clock))->create(username: 'admin', password: $password);
            self::fail('Admin creation must apply the same password policy.');
        } catch (PasswordRejected $exception) {
            self::assertStringContainsString('NUL', $exception->getMessage());
        }

        self::assertNull($this->accounts->authenticate(username: 'alice', password: $password));
        $count = $this->pdo->query(query: 'SELECT count(*) FROM accounts');
        self::assertNotFalse($count);
        self::assertSame(0, (int) $count->fetchColumn());

        (new Register(accounts: $this->accounts, clock: $this->clock))->register(username: 'alice', password: 'valid-password');
        self::assertNull($this->accounts->authenticate(username: 'alice', password: $password));
    }

    /**
     * Verify direct account and admin creation enforce identical password boundary through the public behavior.
     *
     * @return void No return value.
     */
    public function testDirectAccountAndAdminCreationEnforceIdenticalPasswordBoundary(): void
    {
        $password = str_repeat(string: 'b', times: 73);

        try {
            $this->accounts->createUser(username: 'alice', password: $password, createdAt: $this->clock->now());
            self::fail('Direct user creation should reject overlong passwords.');
        } catch (PasswordRejected) {
            self::assertNull($this->accounts->authenticate(username: 'alice', password: $password));
        }

        $this->expectException(PasswordRejected::class);
        (new CreateAdmin(accounts: $this->accounts, clock: $this->clock))->create(username: 'admin', password: $password);
    }
}
