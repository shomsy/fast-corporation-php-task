<?php

declare(strict_types=1);

namespace InterviewTask\Accounts;

use DateTimeImmutable;
use InterviewTask\Accounts\Persistence\SqliteAccounts;
use SensitiveParameter;

/**
 * Owner API for credential policy, account creation and authentication; never knows SQL or PDO.
 *
 * @package InterviewTask\Accounts
 */
final readonly class Accounts
{
    /**
     * Bind private owner policy and concrete persistence without introducing ceremonial interfaces.
     *
     * @param SqliteAccounts     $storage     Owner-private SQLite representation.
     * @param AccountCredentials $credentials Business credential/username policy.
     * @param BcryptPasswords    $bcrypt      Owner-private hashing realization.
     */
    public function __construct(
        private SqliteAccounts     $storage,
        private AccountCredentials $credentials,
        private BcryptPasswords    $bcrypt,
    ) {}

    /**
     * Create a regular user subject to the owner's authoritative credential policy.
     *
     * @param string            $username  Validated identifier candidate.
     * @param string            $password  Secret; never logged.
     * @param DateTimeImmutable $createdAt Creation timestamp.
     *
     * @return Account Created account.
     * @throws PasswordRejected If password violates policy.
     * @throws UsernameRejected If username violates policy.
     * @throws UsernameTaken If SQLite enforces username uniqueness.
     */
    public function createUser(string $username, #[SensitiveParameter] string $password, DateTimeImmutable $createdAt) : Account
    {
        return $this->create(username: $username, password: $password, role: Role::User, createdAt: $createdAt);
    }

    /**
     * Apply one rule set to users and administrators.
     *
     * @param string            $username  Proposed username.
     * @param string            $password  Proposed password.
     * @param Role              $role      Trusted role assigned by the appropriate behavior.
     * @param DateTimeImmutable $createdAt Creation timestamp.
     *
     * @return Account New account.
     * @throws PasswordRejected If password violates policy.
     * @throws UsernameRejected If username violates policy.
     * @throws UsernameTaken If SQLite enforces username uniqueness.
     */
    private function create(string $username, #[SensitiveParameter] string $password, Role $role, DateTimeImmutable $createdAt) : Account
    {
        $this->credentials->validateUsername(username: $username);
        $this->credentials->validatePassword(password: $password);

        return $this->storage->insert(
            username : $username,
            hash     : $this->bcrypt->hash(password: $password),
            role     : $role,
            createdAt: $createdAt,
        );
    }

    /**
     * Trusted CLI creates administrators with the same invariants.
     *
     * @param string            $username  Proposed administrator username.
     * @param string            $password  Secret password.
     * @param DateTimeImmutable $createdAt Creation timestamp.
     *
     * @return Account Created administrator.
     * @throws PasswordRejected If password violates policy.
     * @throws UsernameRejected If username violates policy.
     * @throws UsernameTaken If SQLite enforces username uniqueness.
     */
    public function createAdmin(string $username, #[SensitiveParameter] string $password, DateTimeImmutable $createdAt) : Account
    {
        return $this->create(username: $username, password: $password, role: Role::Admin, createdAt: $createdAt);
    }

    /**
     * Verify credentials without timing shortcut for unknown users, and upgrade old bcrypt cost with CAS.
     *
     * @param string $username Submitted username.
     * @param string $password Secret credential.
     *
     * @return Account|null Verified account, or null for invalid credentials.
     */
    public function authenticate(string $username, #[SensitiveParameter] string $password) : Account|null
    {
        if (! $this->credentials->canAuthenticate(password: $password)) {
            return null;
        }

        $stored = $this->storage->credentialsFor(username: $username);
        if ($stored === null) {
            // Deliberately pay the bcrypt cost even for unknown accounts.
            $this->bcrypt->verify(password: $password, hash: null);

            return null;
        }

        $hash = $stored['hash'];
        if (! $this->bcrypt->verify(password: $password, hash: $hash)) {
            return null;
        }

        $account = $stored['account'];
        if ($hash !== null && $this->bcrypt->needsRehash(hash: $hash)) {
            $this->storage->upgradeHash(
                id          : $account->id,
                newHash     : $this->bcrypt->hash(password: $password),
                previousHash: $hash,
            );
        }

        return $account;
    }

    /**
     * Resolve current account from authoritative storage (including role revocation).
     *
     * @param int $id Authenticated account ID.
     *
     * @return Account|null Stored account, or null if deleted.
     */
    public function findById(int $id) : Account|null
    {
        return $this->storage->findById(id: $id);
    }
}
