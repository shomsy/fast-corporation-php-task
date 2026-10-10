<?php

declare(strict_types=1);

namespace InterviewTask\Accounts\ManageUsers;

use InterviewTask\Accounts\Account;
use InterviewTask\Accounts\Access\RequireAdmin;
use InterviewTask\Accounts\Persistence\SqliteAccounts;
use InterviewTask\Infrastructure\Diagnostics\SecurityEvents;
use InvalidArgumentException;
use Throwable;

/**
 * Account-owned administrator directory and one-way permission grant.
 *
 * @package InterviewTask\Accounts\ManageUsers
 */
final readonly class ManageUsers
{
    /**
     * @param SqliteAccounts $storage Account-owned storage.
     * @param RequireAdmin $admin Trusted session authorization.
     */
    public function __construct(private SqliteAccounts $storage, private RequireAdmin $admin) {}

    /**
     * Return a bounded account directory page without credential details.
     *
     * @param int $afterId Exclusive previous account ID, zero for the first page.
     *
     * @return array{users:list<array{id:int,username:string,role:string}>,nextAfter:int|null} Bounded public page.
     * @throws InvalidArgumentException For an invalid cursor.
     * @throws Throwable When authorization or persistence fails.
     */
    public function listAfter(int $afterId) : array
    {
        $this->admin->account();

        if ($afterId < 0) {
            throw new InvalidArgumentException(message: 'Invalid account cursor.');
        }

        $rows = $this->storage->listAfter(afterId: $afterId, limit: 51);
        $hasMore = count(value: $rows) > 50;
        $users = array_slice(array: $rows, offset: 0, length: 50);

        return [
            'users' => array_map(callback: self::publicAccount(...), array: $users),
            'nextAfter' => $hasMore ? $users[49]->id : null,
        ];
    }

    /**
     * Upgrade only an ordinary user to administrator.
     *
     * @param int $userId Persisted account identifier.
     *
     * @return array{status:'promoted'|'already_admin'|'not_found',user:array{id:int,username:string,role:string}|null} Outcome.
     * @throws InvalidArgumentException For an invalid ID.
     * @throws Throwable When authorization or persistence fails.
     */
    public function promote(int $userId) : array
    {
        $this->admin->account();

        if ($userId <= 0) {
            throw new InvalidArgumentException(message: 'Invalid user ID.');
        }

        if (! $this->storage->promoteToAdmin(id: $userId)) {
            $existing = $this->storage->findById(id: $userId);

            return [
                'status' => $existing === null ? 'not_found' : 'already_admin',
                'user' => $existing === null ? null : self::publicAccount(account: $existing),
            ];
        }

        SecurityEvents::record(event: 'security.admin_granted');
        $updated = $this->storage->findById(id: $userId);

        return [
            'status' => $updated === null ? 'not_found' : 'promoted',
            'user' => $updated === null ? null : self::publicAccount(account: $updated),
        ];
    }

    /**
     * Return a non-sensitive public view of an account.
     *
     * @param Account $account Verified account.
     *
     * @return array{id:int,username:string,role:string} Public account fields.
     */
    private static function publicAccount(Account $account) : array
    {
        return ['id' => $account->id, 'username' => $account->username, 'role' => $account->role->value];
    }
}
