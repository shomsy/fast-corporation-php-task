<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Accounts;

use InterviewTask\Accounts\Login\InvalidCredentials;
use InterviewTask\Accounts\Login\Login;
use InterviewTask\Accounts\Register\Register;
use InterviewTask\Accounts\Role;
use InterviewTask\Accounts\SessionIdentity;

/**
 * Regression checks for Login test, covering observable behavior and failure paths.
 *
 * @package InterviewTask\Tests\Accounts
 */
final class LoginTest extends AccountsTestCase
{
    /**
     * Verify valid credentials regenerate session and establish identity through the public behavior.
     *
     * @return void No return value.
     */
    public function testValidCredentialsRegenerateSessionAndEstablishIdentity() : void
    {
        $account = (new Register(accounts: $this->accounts, clock: $this->clock))->register(username: 'alice', password: 'correct horse battery staple');

        $session = $this->session();
        $session->start();
        $before = session_id();

        $identity = new SessionIdentity(session: $session, accounts: $this->accounts, clock: $this->clock);
        $signedIn = (new Login(accounts: $this->accounts, identity: $identity))->login(username: 'alice', password: 'correct horse battery staple');

        self::assertNotSame($before, session_id());
        self::assertSame($account->id, $signedIn->id);
        self::assertSame('alice', $signedIn->username);
        self::assertSame(Role::User->value, $signedIn->roleCode());
        self::assertEquals($signedIn, $identity->current());
    }

    /**
     * Verify unknown user and wrong password expose same failure through the public behavior.
     *
     * @return void No return value.
     */
    public function testUnknownUserAndWrongPasswordExposeSameFailure() : void
    {
        (new Register(accounts: $this->accounts, clock: $this->clock))->register(username: 'alice', password: 'correct horse battery staple');

        foreach (
            [
                ['missing', 'correct horse battery staple'],
                ['alice', 'wrong password'],
            ] as [$username, $password]
        ) {
            $identity = new SessionIdentity(session: $this->session(), accounts: $this->accounts, clock: $this->clock);
            $login    = new Login(accounts: $this->accounts, identity: $identity);

            try {
                $login->login(username: $username, password: $password);
                self::fail('Expected invalid credentials.');
            } catch (InvalidCredentials $exception) {
                self::assertSame('Invalid username or password.', $exception->getMessage());
                self::assertNull($identity->current());
            }
        }
    }

    /**
     * Unknown accounts run the bcrypt verification path rather than returning immediately.
     *
     * @return void No return value.
     */
    public function testUnknownAccountUsesPasswordVerificationCost() : void
    {
        (new Register(accounts: $this->accounts, clock: $this->clock))
            ->register(username: 'alice', password: 'valid-password');

        $start = hrtime(as_number: true);
        self::assertNull($this->accounts->authenticate(username: 'not-registered', password: 'valid-password'));
        $elapsed = (hrtime(as_number: true) - $start) / 1e9;

        self::assertGreaterThan(0.01, $elapsed, 'Unknown accounts must not bypass password verification cost.');
        self::assertNull($this->accounts->authenticate(username: 'alice', password: 'incorrect-password'));
    }

    /**
     * Successful authentication upgrades an older bcrypt cost and preserves a valid hash.
     *
     * @return void No return value.
     */
    public function testSuccessfulLoginRehashesLegacyBcryptCost() : void
    {
        $account = (new Register(accounts: $this->accounts, clock: $this->clock))
            ->register(username: 'alice', password: 'valid-password');

        $oldHash   = password_hash(password: 'valid-password', algo: PASSWORD_BCRYPT, options: ['cost' => 4]);
        $statement = $this->pdo->prepare(query: 'UPDATE accounts SET password_hash = :hash WHERE id = :id');
        $statement->execute(params: ['hash' => $oldHash, 'id' => $account->id]);

        self::assertNotNull($this->accounts->authenticate(username: 'alice', password: 'valid-password'));
        $statement = $this->pdo->query(query: 'SELECT password_hash FROM accounts LIMIT 1');
        self::assertNotFalse($statement);
        $hash = $statement->fetchColumn();

        self::assertIsString($hash);
        self::assertTrue(password_verify(password: 'valid-password', hash: $hash));
        self::assertSame(12, password_get_info(hash: $hash)['options']['cost']);
    }

}
