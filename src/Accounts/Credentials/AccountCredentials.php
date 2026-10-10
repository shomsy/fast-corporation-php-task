<?php

declare(strict_types=1);

namespace InterviewTask\Accounts\Credentials;

use SensitiveParameter;

/**
 * Single Accounts owner of username rules and credential input limits.
 *
 * @package InterviewTask\Accounts\Credentials
 */
final readonly class AccountCredentials
{
    /**
     * Match ASCII identifiers to SQLite NOCASE uniqueness.
     *
     * @param string $username Proposed account identifier.
     *
     * @return void No return value.
     * @throws UsernameRejected When username violates the owner policy.
     */
    public function validateUsername(string $username) : void
    {
        if (preg_match(pattern: '/\A[A-Za-z0-9][A-Za-z0-9._-]{0,63}\z/', subject: $username) !== 1) {
            throw new UsernameRejected(message: 'Use 1–64 characters for your username. Start with an English letter or number; you can also use dots, underscores, and hyphens.');
        }
    }

    /**
     * Reject bcrypt credential truncation and forbidden bytes before storage.
     *
     * @param string $password Proposed secret password, never logged.
     *
     * @return void No return value.
     * @throws PasswordRejected When the password violates the credential policy.
     */
    public function validatePassword(#[SensitiveParameter] string $password) : void
    {
        if (str_contains(haystack: $password, needle: "\0")) {
            throw new PasswordRejected(message: 'Password contains an unsupported character. Please choose a different password.');
        }

        // Count visible Unicode base characters without PCRE2-version-dependent \X grouping.
        // Combining marks and invisible/control characters must not satisfy the minimum.
        // The independent byte ceiling still prevents silent bcrypt truncation.
        $characters = preg_match_all(pattern: '/[^\p{M}\p{C}]/u', subject: $password);
        if ($characters === false) {
            throw new PasswordRejected(message: 'Password contains an unsupported character. Please choose a different password.');
        }

        if ($characters < 12) {
            throw new PasswordRejected(message: 'Use at least 12 characters for your password.');
        }

        if (strlen(string: $password) > 72) {
            throw new PasswordRejected(message: 'Your password is too long. Try a shorter one, especially if it contains emoji.');
        }
    }

    /**
     * Reject only the disallowed byte-level inputs during login, without changing unknown-user timing.
     *
     * @param string $password Untrusted secret.
     *
     * @return bool Whether verification is permitted.
     */
    public function canAuthenticate(#[SensitiveParameter] string $password) : bool
    {
        return strlen(string: $password) <= 72 && ! str_contains(haystack: $password, needle: "\0");
    }
}
