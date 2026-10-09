<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Accounts;

use InterviewTask\Accounts\AccountCredentials;
use InterviewTask\Accounts\BcryptPasswords;
use InterviewTask\Accounts\PasswordRejected;
use InterviewTask\Accounts\UsernameRejected;
use PHPUnit\Framework\TestCase;

/**
 * Protect domain policy and the bcrypt mechanism independently of SQLite.
 *
 * @package InterviewTask\Tests\Accounts
 */
final class AccountCredentialPolicyTest extends TestCase
{
    /**
     * Verify policy counts bytes, rejects silent bcrypt truncation and disallows NUL.
     *
     * @return void No return value.
     */
    public function testBusinessCredentialLimitsAreEnforcedWithoutPersistence() : void
    {
        $policy = new AccountCredentials();
        $policy->validateUsername(username: 'Alice.2');
        $policy->validatePassword(password: str_repeat(string: 'x', times: 12));
        $policy->validatePassword(password: str_repeat(string: 'x', times: 72));
        self::assertTrue($policy->canAuthenticate(password: str_repeat(string: 'x', times: 72)));
        self::assertFalse($policy->canAuthenticate(password: str_repeat(string: 'x', times: 73)));
        self::assertFalse($policy->canAuthenticate(password: "valid\0password"));
    }

    /**
     * Keep Unicode characters out of SQLite ASCII NOCASE identifiers.
     *
     * @return void No return value.
     */
    public function testUsernameOutsideAsciiPolicyIsRejected() : void
    {
        $this->expectException(UsernameRejected::class);
        (new AccountCredentials())->validateUsername(username: 'miloš');
    }

    /**
     * Credentials shorter than the configured byte minimum are rejected.
     *
     * @return void No return value.
     */
    public function testPasswordBelowByteMinimumIsRejected() : void
    {
        $this->expectException(PasswordRejected::class);
        (new AccountCredentials())->validatePassword(password: str_repeat(string: 'x', times: 11));
    }

    /**
     * Bcrypt behavior is independent from account persistence.
     *
     * @return void No return value.
     */
    public function testBcryptKeepsCostAndUnknownUserVerificationSemantics() : void
    {
        $bcrypt = new BcryptPasswords();
        $hash   = $bcrypt->hash(password: 'strong-password');
        self::assertTrue($bcrypt->verify(password: 'strong-password', hash: $hash));
        self::assertFalse($bcrypt->verify(password: 'wrong-password', hash: $hash));
        self::assertFalse($bcrypt->verify(password: 'strong-password', hash: null));
        self::assertSame(12, password_get_info(hash: $hash)['options']['cost']);
        self::assertFalse($bcrypt->needsRehash(hash: $hash));
        self::assertTrue($bcrypt->needsRehash(hash: password_hash(password: 'strong-password', algo: PASSWORD_BCRYPT, options: ['cost' => 4])));
    }
}
