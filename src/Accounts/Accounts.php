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

    public function createUser(string $username, string $password, DateTimeImmutable $createdAt): Account
    {
        return $this->create($username, $password, Role::User, $createdAt);
    }

    public function createAdmin(string $username, string $password, DateTimeImmutable $createdAt): Account
    {
        return $this->create($username, $password, Role::Admin, $createdAt);
    }

    public function authenticate(string $username, string $password): ?Account
    {
        if (strlen($password) > 72) {
            return null;
        }

        $statement = $this->pdo->prepare(
            'SELECT id, username, password_hash, role, created_at FROM accounts WHERE username = :username LIMIT 1',
        );
        $statement->execute(['username' => $username]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        $hash = $row['password_hash'] ?? null;

        if (!is_string($hash) || !password_verify($password, $hash)) {
            return null;
        }

        $account = $this->accountFromRow($row);

        if (password_needs_rehash($hash, PASSWORD_BCRYPT)) {
            $rehash = password_hash($password, PASSWORD_BCRYPT);
            $update = $this->pdo->prepare('UPDATE accounts SET password_hash = :hash WHERE id = :id');
            $update->execute(['hash' => $rehash, 'id' => $account->id]);
        }

        return $account;
    }

    public function findById(int $id): ?Account
    {
        $statement = $this->pdo->prepare(
            'SELECT id, username, role, created_at FROM accounts WHERE id = :id LIMIT 1',
        );
        $statement->execute(['id' => $id]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->accountFromRow($row) : null;
    }

    private function create(
        string $username,
        string $password,
        Role $role,
        DateTimeImmutable $createdAt,
    ): Account {
        if ($username === '' || $password === '') {
            throw new RuntimeException('Account creation requires non-empty username and password.');
        }

        // Bcrypt silently ignores bytes after 72. Reject rather than truncate credentials.
        if (strlen($password) > 72) {
            throw new PasswordRejected('Password must not exceed 72 bytes.');
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        try {
            $statement = $this->pdo->prepare(
                <<<'SQL'
                INSERT INTO accounts (username, password_hash, role, created_at)
                VALUES (:username, :password_hash, :role, :created_at)
                SQL,
            );
            $statement->execute([
                'username' => $username,
                'password_hash' => $passwordHash,
                'role' => $role->value,
                'created_at' => $createdAt->format('c'),
            ]);
        } catch (PDOException $exception) {
            $driverCode = $exception->errorInfo[1] ?? null;

            if ($exception->getCode() === '23000' || $driverCode === 19) {
                throw new UsernameTaken(previous: $exception);
            }

            throw $exception;
        }

        $id = (int) $this->pdo->lastInsertId();

        return new Account($id, $username, $role, $createdAt);
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private function accountFromRow(array $row): Account
    {
        $id = $row['id'] ?? null;
        $username = $row['username'] ?? null;
        $role = $row['role'] ?? null;
        $createdAt = $row['created_at'] ?? null;

        if (
            (!is_int($id) && !is_string($id))
            || !is_string($username)
            || !is_string($role)
            || !is_string($createdAt)
        ) {
            throw new RuntimeException('Stored account row is invalid.');
        }

        return new Account(
            (int) $id,
            $username,
            Role::from($role),
            new DateTimeImmutable($createdAt),
        );
    }
}
