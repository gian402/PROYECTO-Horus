<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::UsersManage->value);
    }

    public function update(User $user, User $model): bool
    {
        return $user->can(Permission::UsersManage->value);
    }
}
