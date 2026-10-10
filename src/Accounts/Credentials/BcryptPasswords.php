<?php

declare(strict_types=1);

namespace InterviewTask\Accounts\Credentials;

use SensitiveParameter;
use ValueError;

/**
 * Private bcrypt realization; the owner holds password policy separately.
 *
 * @package InterviewTask\Accounts\Credentials
 */
final readonly class BcryptPasswords
{
    /**
     * Stable password work factor across PHP versions.
     *
     * @var int
     */
    private const int COST = 12;

    /**
     * A fixed dummy bcrypt hash for unknown accounts; never represents a real account.
     *
     * @var string
     */
    private const string UNKNOWN_USER_HASH = '$2y$12$m75UX8xqNpe8KeCsn9hILeogdQ76jM3GTqAxJmooNGoZvJ4MHo69q';

    /**
     * Create a credential hash with a deterministic policy cost.
     *
     * @param string $password Secret bytes.
     *
     * @return string Bcrypt credential hash.
     * @throws ValueError When secret bytes cannot be hashed or the salt source fails; an entropy failure is chained.
     */
    public function hash(#[SensitiveParameter] string $password) : string
    {
        return password_hash(password: $password, algo: PASSWORD_BCRYPT, options: ['cost' => self::COST]);
    }

    /**
     * Verify a submitted secret without revealing differences through response messages.
     *
     * @param string      $password Submitted secret.
     * @param string|null $hash     Stored bcrypt hash; null when the account is absent.
     *
     * @return bool Whether submitted credentials match a real account.
     */
    public function verify(#[SensitiveParameter] string $password, string|null $hash) : bool
    {
        if ($hash === null) {
            password_verify(password: $password, hash: self::UNKNOWN_USER_HASH);

            return false;
        }

        return password_verify(password: $password, hash: $hash);
    }

    /**
     * Detect an older work factor after valid authentication.
     *
     * @param string $hash Verified stored hash.
     *
     * @return bool Whether hash upgrade is needed.
     */
    public function needsRehash(string $hash) : bool
    {
        return password_needs_rehash(hash: $hash, algo: PASSWORD_BCRYPT, options: ['cost' => self::COST]);
    }
}
