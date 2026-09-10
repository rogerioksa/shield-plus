<?php

declare(strict_types=1);

namespace Securyt\Acl\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Securyt\Acl\Support\Acl;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can(Acl::permission('ViewAny', 'Role'));
    }

    public function view(AuthUser $authUser, Role $role): bool
    {
        return $authUser->can(Acl::permission('View', 'Role'));
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can(Acl::permission('Create', 'Role'));
    }

    public function update(AuthUser $authUser, Role $role): bool
    {
        return $authUser->can(Acl::permission('Update', 'Role'));
    }

    public function delete(AuthUser $authUser, Role $role): bool
    {
        return $authUser->can(Acl::permission('Delete', 'Role'));
    }
}
