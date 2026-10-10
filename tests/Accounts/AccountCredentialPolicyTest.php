<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Accounts;

use InterviewTask\Accounts\Credentials\AccountCredentials;
use InterviewTask\Accounts\Credentials\BcryptPasswords;
use InterviewTask\Accounts\Credentials\PasswordRejected;
use InterviewTask\Accounts\Credentials\UsernameRejected;
use PHPUnit\Framework\TestCase;

/**
 * Protect domain policy and the bcrypt mechanism independently of SQLite.
 *
 * @package InterviewTask\Tests\Accounts
 */
final class AccountCredentialPolicyTest extends TestCase
{
    /**
     * Verify visible-character minimum, byte ceiling, and forbidden byte policy.
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
     * Passwords shorter than the configured visible-character minimum are rejected.
     *
     * @return void No return value.
     */
    public function testPasswordBelowCharacterMinimumIsRejected() : void
    {
        $this->expectException(PasswordRejected::class);
        (new AccountCredentials())->validatePassword(password: str_repeat(string: 'x', times: 11));
    }

    /**
     * Count visible Unicode base characters, not UTF-8 bytes, across PCRE2 versions.
     * The separate bcrypt ceiling still rejects inputs over 72 bytes.
     *
     * @return void No return value.
     */
    public function testUnicodePasswordLengthMatchesUserFacingFeedback() : void
    {
        $policy = new AccountCredentials();
        $policy->validatePassword(password: str_repeat(string: 'é', times: 12));
        $policy->validatePassword(password: str_repeat(string: '🔒', times: 12));

        try {
            $policy->validatePassword(password: str_repeat(string: '🔒', times: 11));
            self::fail('11 visible characters must be rejected.');
        } catch (PasswordRejected $exception) {
            self::assertSame('Use at least 12 characters for your password.', $exception->getMessage());
        }

        try {
            $policy->validatePassword(password: str_repeat(string: '🔒', times: 20));
            self::fail('Long Unicode passwords must not be silently truncated by bcrypt.');
        } catch (PasswordRejected $exception) {
            self::assertSame('Your password is too long. Try a shorter one, especially if it contains emoji.', $exception->getMessage());
        }
    }

    /**
     * Combining marks, invisible formatting and invalid UTF-8 cannot bypass the password minimum.
     *
     * @return void No return value.
     */
    public function testUnicodeModifiersDoNotInflateMinimumLength() : void
    {
        $policy = new AccountCredentials();

        foreach ([
            str_repeat(string: "e\u{0301}", times: 11),
            str_repeat(string: "\u{200D}🔒", times: 11),
            str_repeat(string: "\u{0301}", times: 12),
        ] as $password) {
            try {
                $policy->validatePassword(password: $password);
                self::fail('Invisible or combining Unicode characters must not meet the minimum.');
            } catch (PasswordRejected $exception) {
                self::assertSame('Use at least 12 characters for your password.', $exception->getMessage());
            }
        }

        $policy->validatePassword(password: str_repeat(string: "e\u{0301}", times: 12));

        try {
            $policy->validatePassword(password: str_repeat(string: 'x', times: 12) . "\xFF");
            self::fail('Invalid UTF-8 must be rejected.');
        } catch (PasswordRejected $exception) {
            self::assertSame('Password contains an unsupported character. Please choose a different password.', $exception->getMessage());
        }
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
