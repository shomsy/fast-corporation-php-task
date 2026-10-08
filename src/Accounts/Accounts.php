<?php

declare(strict_types=1);

namespace InterviewTask\Accounts;

use DateTimeImmutable;
use PDO;
use PDOException;
use RuntimeException;

final readonly class Accounts
{
    public function __construct(private PDO $pdo) {}

    public function createUser(string $username, #[\SensitiveParameter] string $password, DateTimeImmutable $createdAt): Account
    {
        return $this->create(username: $username, password: $password, role: Role::User, createdAt: $createdAt);
    }

    private function create(
        string            $username,
        #[\SensitiveParameter]
        string $password,
        Role              $role,
        DateTimeImmutable $createdAt,
    ): Account {
        if ($username === '' || $password === '') {
            throw new RuntimeException(message: 'Account creation requires non-empty username and password.');
        }

        // Bcrypt silently ignores bytes after 72. Reject rather than truncate credentials.
        if (strlen(string: $password) > 72) {
            throw new PasswordRejected(message: 'Password must not exceed 72 bytes.');
        }

        if (str_contains(haystack: $password, needle: "\0")) {
            throw new PasswordRejected(message: 'Password must not contain NUL bytes.');
        }

        $passwordHash = password_hash(password: $password, algo: PASSWORD_BCRYPT);

        try {
            $statement = $this->pdo->prepare(
                query: <<<'SQL'
                    INSERT INTO accounts (username, password_hash, role, created_at)
                    VALUES (:username, :password_hash, :role, :created_at)
                    SQL,
            );
            $statement->execute(params: [
                'username'      => $username,
                'password_hash' => $passwordHash,
                'role'          => $role->value,
                'created_at'    => $createdAt->format(format: 'c'),
            ]);
        } catch (PDOException $exception) {
            // SQLite code 19 also covers CHECK, NOT NULL, FK and trigger failures.
            // Only the named username UNIQUE constraint is a duplicate username.
            $driverCode    = $exception->errorInfo[1] ?? null;
            $driverMessage = $exception->errorInfo[2] ?? null;
            if (
                $this->pdo->getAttribute(attribute: PDO::ATTR_DRIVER_NAME) === 'sqlite'
                && in_array(needle: $driverCode, haystack: [19, 2067, '19', '2067'], strict: true)
                && $driverMessage === 'UNIQUE constraint failed: accounts.username'
            ) {
                throw new UsernameTaken(previous: $exception);
            }

            throw $exception;
        }

        $id = (int) $this->pdo->lastInsertId();

        return new Account(id: $id, username: $username, role: $role, createdAt: $createdAt);
    }

    public function createAdmin(string $username, #[\SensitiveParameter] string $password, DateTimeImmutable $createdAt): Account
    {
        return $this->create(username: $username, password: $password, role: Role::Admin, createdAt: $createdAt);
    }

    public function authenticate(string $username, #[\SensitiveParameter] string $password): ?Account
    {
        if (strlen(string: $password) > 72 || str_contains(haystack: $password, needle: "\0")) {
            return null;
        }

        $statement = $this->pdo->prepare(
            query: 'SELECT id, username, password_hash, role, created_at FROM accounts WHERE username = :username LIMIT 1',
        );
        $statement->execute(params: ['username' => $username]);

        $row = $statement->fetch(mode: PDO::FETCH_ASSOC);

        if (! is_array(value: $row)) {
            return null;
        }

        $hash = $row['password_hash'] ?? null;

        if (! is_string(value: $hash) || ! password_verify(password: $password, hash: $hash)) {
            return null;
        }

        $account = $this->accountFromRow(row: $row);

        if (password_needs_rehash(hash: $hash, algo: PASSWORD_BCRYPT)) {
            $rehash = password_hash(password: $password, algo: PASSWORD_BCRYPT);
            $update = $this->pdo->prepare(query: 'UPDATE accounts SET password_hash = :hash WHERE id = :id');
            $update->execute(params: ['hash' => $rehash, 'id' => $account->id]);
        }

        return $account;
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private function accountFromRow(array $row): Account
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
            id: (int) $id,
            username: $username,
            role: Role::from(value: $role),
            createdAt: new DateTimeImmutable(datetime: $createdAt),
        );
    }

    public function findById(int $id): ?Account
    {
        $statement = $this->pdo->prepare(
            query: 'SELECT id, username, role, created_at FROM accounts WHERE id = :id LIMIT 1',
        );
        $statement->execute(params: ['id' => $id]);

        $row = $statement->fetch(mode: PDO::FETCH_ASSOC);

        return is_array(value: $row) ? $this->accountFromRow(row: $row) : null;
    }
}
