<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Accounts;

use InterviewTask\Accounts\Access\AccessDenied;
use InterviewTask\Accounts\Access\RequireAdmin;
use InterviewTask\Accounts\Access\SessionIdentity;
use InterviewTask\Accounts\CreateAdmin\CreateAdmin;
use InterviewTask\Accounts\ManageUsers\ManageUsers;
use InterviewTask\Accounts\Persistence\SqliteAccounts;
use InterviewTask\Accounts\Register\Register;
use InterviewTask\Accounts\Role;
use InterviewTask\Bootstrap\Http\ManageUsersApi;
use InterviewTask\Runtime\Http\Request;

/**
 * Account management policy and API contracts with isolated real SQLite storage.
 *
 * @package InterviewTask\Tests\Accounts
 */
final class ManageUsersTest extends AccountsTestCase
{
    /**
     * Verify an ordinary user cannot list accounts or upgrade themselves.
     *
     * @return void No return value.
     */
    public function testRegularUserCannotGrantTheirOwnAdministratorPrivileges() : void
    {
        $user = (new Register(accounts: $this->accounts, clock: $this->clock))
            ->register(username: 'alice', password: 'strong-password');

        $identity = new SessionIdentity(session: $this->session(), accounts: $this->accounts, clock: $this->clock, cleanupFailures: $this->cleanupFailures);
        $identity->signIn(account: $user);
        $manager = new ManageUsers(storage: new SqliteAccounts(pdo: $this->pdo), admin: new RequireAdmin(identity: $identity));

        try {
            $manager->listAfter(afterId: 0);
            self::fail('Ordinary user listed private accounts.');
        } catch (AccessDenied) {
            self::assertSame('user', $identity->current()?->roleCode());
        }

        $this->expectException(AccessDenied::class);
        $manager->promote(userId: $user->id);
    }

    /**
     * Verify an administrator can grant exactly one role upgrade and avoid duplicate writes.
     *
     * @return void No return value.
     */
    public function testAdministratorPromotesUserWithoutChangingTheirPassword() : void
    {
        $admin = (new CreateAdmin(accounts: $this->accounts, clock: $this->clock))
            ->create(username: 'root-admin', password: 'strong-password');
        $user = (new Register(accounts: $this->accounts, clock: $this->clock))
            ->register(username: 'alice', password: 'strong-password');

        $identity = new SessionIdentity(session: $this->session(), accounts: $this->accounts, clock: $this->clock, cleanupFailures: $this->cleanupFailures);
        $identity->signIn(account: $admin);
        $manager = new ManageUsers(storage: new SqliteAccounts(pdo: $this->pdo), admin: new RequireAdmin(identity: $identity));

        $directory = $manager->listAfter(afterId: 0);
        self::assertCount(2, $directory['users']);
        self::assertNull($directory['nextAfter']);
        self::assertSame(Role::User->value, $directory['users'][1]['role']);

        $updated = $manager->promote(userId: $user->id);
        self::assertSame('promoted', $updated['status']);
        self::assertIsArray($updated['user']);
        self::assertSame(Role::Admin->value, $updated['user']['role']);
        self::assertSame(Role::Admin, $this->accounts->authenticate(username: 'alice', password: 'strong-password')?->role);

        self::assertSame('already_admin', $manager->promote(userId: $user->id)['status']);
        self::assertSame(Role::Admin, $this->accounts->findById(id: $user->id)?->role);

        $identity->signOut();
        $identity->signIn(account: $user);
        self::assertSame('admin', $identity->current()?->roleCode());
        self::assertSame($user->id, (new RequireAdmin(identity: $identity))->account()->id);
    }

    /**
     * Verify bounded listing, public response projection, and invalid IDs.
     *
     * @return void No return value.
     */
    public function testDirectoryApiAndMissingOrMalformedPromotions() : void
    {
        $admin = (new CreateAdmin(accounts: $this->accounts, clock: $this->clock))
            ->create(username: 'root-admin', password: 'strong-password');
        $identity = new SessionIdentity(session: $this->session(), accounts: $this->accounts, clock: $this->clock, cleanupFailures: $this->cleanupFailures);
        $identity->signIn(account: $admin);
        $api = new ManageUsersApi(users: new ManageUsers(
            storage: new SqliteAccounts(pdo: $this->pdo),
            admin: new RequireAdmin(identity: $identity),
        ));

        $listing = $api->index(request: new Request(method: 'GET', path: '/api/accounts/users'));
        self::assertSame(200, $listing->status());
        $response = json_decode(json: $listing->body(), associative: true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($response);
        self::assertIsArray($response['users']);
        self::assertCount(1, $response['users']);
        self::assertIsArray($response['users'][0]);
        self::assertSame(['id', 'username', 'role'], array_keys(array: $response['users'][0]));
        self::assertNull($response['nextAfter']);

        $invalidCursor = $api->index(request: new Request(method: 'GET', path: '/api/accounts/users', query: ['after' => ['not-valid']]));
        self::assertSame(422, $invalidCursor->status());

        $missing = $api->promote(request: new Request(method: 'POST', path: '/api/accounts/users/promote',
            server: ['CONTENT_TYPE' => 'application/json'], rawBody: '{"userId":999999}'));
        self::assertSame(404, $missing->status());

        foreach (['{"userId":"1"}', '{"userId":0}', '{"userId":true}', '{"userId":1,"role":"admin"}'] as $payload) {
            $invalid = $api->promote(request: new Request(method: 'POST', path: '/api/accounts/users/promote',
                server: ['CONTENT_TYPE' => 'application/json'], rawBody: $payload));
            // Additional, untrusted fields cannot authorize or change an already-admin account.
            self::assertContains($invalid->status(), [409, 422]);
        }

        $manager = new ManageUsers(storage: new SqliteAccounts(pdo: $this->pdo), admin: new RequireAdmin(identity: $identity));
        self::assertSame('not_found', $manager->promote(userId: 999999)['status']);
    }
}
