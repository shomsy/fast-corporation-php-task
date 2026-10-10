<?php

declare(strict_types=1);

namespace InterviewTask\Accounts\Access;

use InterviewTask\Accounts\Role;
use ValueError;

/**
 * Minimal authenticated identity exposed by the Accounts owner to other modules.
 *
 * @api
 * @package InterviewTask\Accounts\Access
 */
final readonly class SignedInAccount
{
    /**
     * Keep the internal Role enum private while accepting the stable public role value.
     *
     * @param int    $id       Authenticated account ID.
     * @param string $username Authenticated account username.
     * @param string $role     Canonical persisted role code, user or admin.
     *
     * @throws ValueError When a role value is unknown.
     */
    public function __construct(
        public int    $id,
        public string $username,
        string        $role,
    )
    {
        $this->role = Role::from(value: $role);
    }

    /**
     * Private validated authorization role, never part of the exported object shape.
     *
     * @var Role
     */
    private Role $role;

    /**
     * Export the canonical role code without leaking the private enum type.
     *
     * @return string Public role code, user or admin.
     */
    public function roleCode() : string
    {
        return $this->role->value;
    }

    /**
     * Answer the public authorization question without exposing the internal Role type.
     *
     * @return bool Whether this verified identity has administrator privileges.
     */
    public function isAdmin() : bool
    {
        return $this->role === Role::Admin;
    }
}
