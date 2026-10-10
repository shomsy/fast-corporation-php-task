<?php

declare(strict_types=1);

namespace InterviewTask\Accounts\Persistence;

use DateTimeImmutable;
use DateMalformedStringException;
use InterviewTask\Accounts\Account;
use InterviewTask\Accounts\Role;
use InterviewTask\Accounts\UsernameTaken;
use PDO;
use PDOException;
use RuntimeException;
use SensitiveParameter;
use ValueError;

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
     * @throws RuntimeException If stored account fields are missing or have invalid types.
     * @throws ValueError If the stored role is unknown.
     * @throws DateMalformedStringException If the stored creation timestamp cannot be parsed.
     * @throws PDOException When credential lookup fails.
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
     * @throws PDOException When the conditional credential update fails.
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
     * @throws PDOException When account lookup fails.
     * @throws RuntimeException If stored account fields are missing or have invalid types.
     * @throws ValueError If the stored role is unknown.
     * @throws DateMalformedStringException If the stored creation timestamp cannot be parsed.
     */
    public function findById(int $id) : Account|null
    {
        $statement = $this->pdo->prepare(query: 'SELECT id, username, role, created_at FROM accounts WHERE id = :id LIMIT 1');
        $statement->execute(params: ['id' => $id]);
        $row = $statement->fetch(mode: PDO::FETCH_ASSOC);

        return is_array(value: $row) ? $this->accountFromRow(row: $row) : null;
    }

    /**
     * List at most a bounded number of accounts in stable insertion order.
     *
     * @param int $afterId Exclusive last seen identifier.
     * @param int $limit Maximum account count (at most 51).
     *
     * @return list<Account> Stored public account snapshots.
     * @throws PDOException When reading fails.
     * @throws RuntimeException When stored data is invalid.
     */
    public function listAfter(int $afterId, int $limit) : array
    {
        if ($afterId < 0 || $limit < 1 || $limit > 51) {
            throw new RuntimeException(message: 'Invalid account pagination bounds.');
        }

        $statement = $this->pdo->prepare(query: 'SELECT id, username, role, created_at FROM accounts WHERE id > :after ORDER BY id ASC LIMIT :limit');
        $statement->bindValue(param: ':after', value: $afterId, type: PDO::PARAM_INT);
        $statement->bindValue(param: ':limit', value: $limit, type: PDO::PARAM_INT);
        $statement->execute();

        $accounts = [];
        while (($row = $statement->fetch(mode: PDO::FETCH_ASSOC)) !== false) {
            $accounts[] = $this->accountFromRow(row: $row);
        }

        return $accounts;
    }

    /**
     * Atomically promote only a current ordinary user; a repeated grant changes no rows.
     *
     * @param int $id Persisted account identifier.
     *
     * @return bool Whether the role was changed from user to admin.
     * @throws PDOException When the update fails.
     */
    public function promoteToAdmin(int $id) : bool
    {
        $statement = $this->pdo->prepare(query: "UPDATE accounts SET role = 'admin' WHERE id = :id AND role = 'user'");
        $statement->execute(params: ['id' => $id]);

        return $statement->rowCount() === 1;
    }

    /**
     * Validate SQLite fields and map one row into owner-internal Account.
     *
     * @param array<array-key, mixed> $row Raw database record.
     *
     * @return Account Validated account snapshot.
     * @throws RuntimeException When stored fields are missing or have invalid types.
     * @throws ValueError When the stored role is unknown.
     * @throws DateMalformedStringException When the stored creation timestamp cannot be parsed.
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
