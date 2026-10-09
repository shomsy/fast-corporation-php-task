<?php

declare(strict_types=1);

namespace InterviewTask\Accounts\Persistence;

use DateTimeImmutable;
use InterviewTask\Accounts\Account;
use InterviewTask\Accounts\Role;
use InterviewTask\Accounts\UsernameTaken;
use PDO;
use PDOException;
use RuntimeException;
use SensitiveParameter;

/**
 * Owner-private SQLite mapping and SQL; contains no password policy or business orchestration.
 *
 * @package InterviewTask\Accounts\Persistence
 */
final readonly class SqliteAccounts
{
    /**
     * @param PDO $pdo Configured SQLite connection; transactions are owned by the caller.
     */
    public function __construct(private PDO $pdo) {}

    /**
     * Persist a validated account and translate its exact username uniqueness failure.
     *
     * @param string            $username  Validated username.
     * @param string            $hash      Stored hash, never plaintext.
     * @param Role              $role      Owner-authorized role.
     * @param DateTimeImmutable $createdAt Creation time.
     *
     * @return Account Newly persisted account.
     * @throws UsernameTaken When SQLite reports a duplicate username.
     * @throws PDOException When database persistence fails.
     */
    public function insert(string $username, #[SensitiveParameter] string $hash, Role $role, DateTimeImmutable $createdAt) : Account
    {
        try {
            $statement = $this->pdo->prepare(
                query: <<<'SQL'
                    INSERT INTO accounts (username, password_hash, role, created_at)
                    VALUES (:username, :password_hash, :role, :created_at)
                    SQL,
            );
            $statement->execute(params: [
                                            'username'      => $username,
                                            'password_hash' => $hash,
                                            'role'          => $role->value,
                                            'created_at'    => $createdAt->format(format: 'c'),
                                        ]);
        } catch (PDOException $exception) {
            $driverCode    = $exception->errorInfo[1] ?? null;
            $driverMessage = $exception->errorInfo[2] ?? null;
            if (
                $driverMessage === 'UNIQUE constraint failed: accounts.username'
                && in_array(needle: $driverCode, haystack: [19, 2067, '19', '2067'], strict: true)
                && $this->pdo->getAttribute(attribute: PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ) {
                throw new UsernameTaken(previous: $exception);
            }

            throw $exception;
        }

        return new Account(id: (int) $this->pdo->lastInsertId(), username: $username, role: $role, createdAt: $createdAt);
    }

    /**
     * Retrieve a stored credential with its typed identity for owner-only verification.
     *
     * @param string $username Username to look up.
     *
     * @return StoredCredentials|null Stored account and hash, or null.
     * @throws RuntimeException If stored account fields are malformed.
     */
    public function credentialsFor(string $username) : StoredCredentials|null
    {
        $statement = $this->pdo->prepare(query: 'SELECT id, username, password_hash, role, created_at FROM accounts WHERE username = :username LIMIT 1');
        $statement->execute(params: ['username' => $username]);
        $row = $statement->fetch(mode: PDO::FETCH_ASSOC);
        if (! is_array(value: $row)) {
            return null;
        }

        $hash = $row['password_hash'] ?? null;

        return new StoredCredentials(
            account: $this->accountFromRow(row: $row),
            hash   : is_string(value: $hash) ? $hash : null,
        );
    }

    /**
     * Replace an authenticated hash only when a concurrent password change has not occurred.
     *
     * @param int    $id           Account ID.
     * @param string $newHash      Next bcrypt hash.
     * @param string $previousHash Previously verified hash.
     *
     * @return void No return value.
     */
    public function upgradeHash(int $id, #[SensitiveParameter] string $newHash, #[SensitiveParameter] string $previousHash) : void
    {
        $update = $this->pdo->prepare(query: 'UPDATE accounts SET password_hash = :hash WHERE id = :id AND password_hash = :previous');
        $update->execute(params: ['hash' => $newHash, 'id' => $id, 'previous' => $previousHash]);
    }

    /**
     * Find an account from current authoritative storage for session verification.
     *
     * @param int $id Account ID.
     *
     * @return Account|null Stored identity, or null after deletion.
     */
    public function findById(int $id) : Account|null
    {
        $statement = $this->pdo->prepare(query: 'SELECT id, username, role, created_at FROM accounts WHERE id = :id LIMIT 1');
        $statement->execute(params: ['id' => $id]);
        $row = $statement->fetch(mode: PDO::FETCH_ASSOC);

        return is_array(value: $row) ? $this->accountFromRow(row: $row) : null;
    }

    /**
     * Validate SQLite fields and map one row into owner-internal Account.
     *
     * @param array<array-key, mixed> $row Raw database record.
     *
     * @return Account Validated account snapshot.
     * @throws RuntimeException When stored fields are missing or malformed.
     */
    private function accountFromRow(array $row) : Account
    {
        $id        = $row['id'] ?? null;
        $username  = $row['username'] ?? null;
        $role      = $row['role'] ?? null;
        $createdAt = $row['created_at'] ?? null;

        if (
            (! is_int(value: $id) && ! is_string(value: $id))
            || ! is_string(value: $username)
            || ! is_string(value: $role)
            || ! is_string(value: $createdAt)
        ) {
            throw new RuntimeException(message: 'Stored account row is invalid.');
        }

        return new Account(
            id       : (int) $id,
            username : $username,
            role     : Role::from(value: $role),
            createdAt: new DateTimeImmutable(datetime: $createdAt),
        );
    }
}
